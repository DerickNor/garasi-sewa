<?php

namespace Tests\Unit;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit test RentalService dengan database: penyimpanan sewa baru (REQ-RENT-06, REQ-RENT-08, BR-08, BR-10).
 */
class RentalServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalService $service;

    private User $renter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 08:00');
        Storage::fake('public');

        $this->service = app(RentalService::class);
        $this->renter = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(Garage $garage, array $overrides = []): array
    {
        return array_merge([
            'vehicle_type' => 'car',
            'plate_number' => 'L 1234 AB',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'battery_capacity_kwh' => null,
            'battery_start_kwh' => null,
            'rental_type' => 'daily',
            'days' => 3,
            'start_date' => '2026-11-21',
            'garage_id' => $garage->id,
        ], $overrides);
    }

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->image('bukti.jpg');
    }

    private function carGarage(string $code = 'C901', float $price = 100000): Garage
    {
        return Garage::factory()->ofType(GarageType::CAR)->create(['code' => $code, 'daily_price' => $price]);
    }

    private function assertNothingSaved(Garage $garage): void
    {
        $this->assertSame(0, Rental::count());
        $this->assertSame(0, Vehicle::count());
        $this->assertSame(GarageStatus::AVAILABLE, $garage->refresh()->status);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** TC-053: sewa harian valid tersimpan, garasi used, harga snapshot terisi. */
    public function test_tc053_daily_rental_is_saved(): void
    {
        $garage = $this->carGarage();

        $rental = $this->service->createRental($this->renter, $this->input($garage), $this->proof());

        $this->assertSame(RentalType::DAILY, $rental->rental_type);
        $this->assertSame(3, $rental->duration_days);
        $this->assertSame('2026-11-21', $rental->start_date->toDateString());
        $this->assertSame('2026-11-24', $rental->end_date->toDateString());
        $this->assertEquals(100000, $rental->daily_price);
        $this->assertEquals(300000, $rental->rental_fee);
        $this->assertEquals(0, $rental->charging_fee);
        $this->assertEquals(300000, $rental->total_cost);
        $this->assertTrue($rental->user->is($this->renter));
        $this->assertSame('L 1234 AB', $rental->vehicle->plate_number);
        $this->assertSame(VehicleType::CAR, $rental->vehicle->type);
        $this->assertSame(GarageStatus::USED, $garage->refresh()->status);
        Storage::disk('public')->assertExists($rental->payment_proof_path);
    }

    /** TC-054: sewa bulanan = 25 × harian, durasi = jumlah hari bulan mulai. */
    public function test_tc054_monthly_rental(): void
    {
        $garage = $this->carGarage();

        $rental = $this->service->createRental(
            $this->renter,
            $this->input($garage, ['rental_type' => 'monthly', 'days' => null]),
            $this->proof(),
        );

        $this->assertSame(30, $rental->duration_days);
        $this->assertSame('2026-12-21', $rental->end_date->toDateString());
        $this->assertEquals(2500000, $rental->rental_fee);
    }

    /** Mobil listrik di garasi E: biaya isi listrik, baterai awal, dan harga kWh tersimpan. */
    public function test_electric_car_in_electric_garage(): void
    {
        $garage = Garage::factory()->ofType(GarageType::ELECTRIC_CAR)->create(['code' => 'E901', 'daily_price' => 200000]);

        $rental = $this->service->createRental($this->renter, $this->input($garage, [
            'vehicle_type' => 'electric_car',
            'battery_capacity_kwh' => 60,
            'battery_start_kwh' => 22.5,
        ]), $this->proof());

        $this->assertEquals(93750, $rental->charging_fee);
        $this->assertEquals(693750, $rental->total_cost);
        $this->assertEquals(2500, $rental->kwh_price);
        $this->assertEquals(22.5, $rental->battery_start_kwh);
        $this->assertEquals(60, $rental->vehicle->battery_capacity_kwh);
    }

    /** TC-056: mobil ke garasi sepeda ditolak di server. */
    public function test_tc056_incompatible_garage_is_rejected(): void
    {
        $garage = Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S901']);

        $this->expectRejected(fn () => $this->service->createRental($this->renter, $this->input($garage), $this->proof()));

        $this->assertNothingSaved($garage);
    }

    /** TC-057: garasi yang sudah disewa pada rentang yang bertabrakan ditolak. */
    public function test_tc057_unavailable_garage_is_rejected(): void
    {
        $garage = $this->carGarage();
        Rental::factory()->for($garage)->between('2026-11-23', '2026-11-26')->create();
        $garage->update(['status' => GarageStatus::AVAILABLE]);
        $rentalsBefore = Rental::count();
        $vehiclesBefore = Vehicle::count();

        $this->expectRejected(fn () => $this->service->createRental($this->renter, $this->input($garage), $this->proof()));

        $this->assertSame($rentalsBefore, Rental::count());
        $this->assertSame($vehiclesBefore, Vehicle::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** TC-059: nilai biaya dari browser diabaikan, server menghitung sendiri. */
    public function test_tc059_client_totals_are_ignored(): void
    {
        $garage = $this->carGarage();

        $rental = $this->service->createRental($this->renter, $this->input($garage, [
            'total_cost' => 1,
            'rental_fee' => 1,
            'daily_price' => 1,
        ]), $this->proof());

        $this->assertEquals(300000, $rental->total_cost);
        $this->assertEquals(100000, $rental->daily_price);
    }

    /** TC-062: plat yang punya sewa bertabrakan ditolak; rentang lain diterima; sepeda tidak dicek. */
    public function test_tc062_same_plate_cannot_have_overlapping_rentals(): void
    {
        $first = $this->carGarage('C901');
        $second = $this->carGarage('C902');
        $this->service->createRental($this->renter, $this->input($first), $this->proof());

        $otherRenter = User::factory()->create();
        $this->expectRejected(fn () => $this->service->createRental(
            $otherRenter,
            $this->input($second, ['plate_number' => 'l1234ab']),
            $this->proof(),
        ));

        $later = $this->service->createRental(
            $this->renter,
            $this->input($second, ['start_date' => '2026-11-24']),
            $this->proof(),
        );
        $this->assertSame('2026-11-24', $later->start_date->toDateString());
        $this->assertSame(1, Vehicle::where('plate_number', 'L 1234 AB')->count());

        $bikeA = Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S901']);
        $bikeB = Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S902']);
        $bicycle = ['vehicle_type' => 'bicycle', 'plate_number' => null, 'brand' => 'Polygon', 'model' => 'Xtrada'];
        $this->service->createRental($this->renter, $this->input($bikeA, $bicycle), $this->proof());
        $this->service->createRental($this->renter, $this->input($bikeB, $bicycle), $this->proof());

        $this->assertSame(4, Rental::count());
    }

    /** TC-063: satu penyewa boleh menyewa dua garasi untuk dua kendaraan sekaligus. */
    public function test_tc063_one_renter_two_garages(): void
    {
        $this->service->createRental($this->renter, $this->input($this->carGarage('C901')), $this->proof());
        $this->service->createRental(
            $this->renter,
            $this->input($this->carGarage('C902'), ['plate_number' => 'L 5678 CD']),
            $this->proof(),
        );

        $this->assertSame(2, $this->renter->rentals()->count());
        $this->assertSame(2, $this->renter->vehicles()->count());
    }

    /** TC-064: tanggal mulai di masa depan, tanggal selesai dihitung dari tanggal mulai. */
    public function test_tc064_future_start_date(): void
    {
        $rental = $this->service->createRental(
            $this->renter,
            $this->input($this->carGarage(), ['start_date' => '2027-01-02', 'days' => 3]),
            $this->proof(),
        );

        $this->assertSame('2027-01-02', $rental->start_date->toDateString());
        $this->assertSame('2027-01-05', $rental->end_date->toDateString());
        $this->assertSame(GarageStatus::USED, $rental->garage->status);
    }

    /** TC-065: kegagalan saat menyimpan membatalkan semuanya, garasi tetap available. */
    public function test_tc065_failure_rolls_back_everything(): void
    {
        $garage = $this->carGarage();
        Rental::creating(fn () => throw new RuntimeException('Simulasi kegagalan database'));

        try {
            $this->service->createRental($this->renter, $this->input($garage), $this->proof());
            $this->fail('Penyimpanan seharusnya gagal');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulasi kegagalan database', $e->getMessage());
        } finally {
            Rental::flushEventListeners();
        }

        $this->assertNothingSaved($garage);
    }

    /** BR-08: perubahan harga garasi setelah sewa tidak mengubah harga di data sewa. */
    public function test_price_snapshot_is_kept(): void
    {
        $garage = $this->carGarage();
        $rental = $this->service->createRental($this->renter, $this->input($garage), $this->proof());

        $garage->update(['daily_price' => 999999]);

        $this->assertEquals(100000, $rental->refresh()->daily_price);
    }

    /** previewRental menghitung biaya dari harga garasi tanpa menyimpan apa pun. */
    public function test_preview_does_not_save(): void
    {
        $garage = $this->carGarage();

        $preview = $this->service->previewRental($this->input($garage, ['rental_type' => 'monthly', 'days' => null]));

        $this->assertEquals(2500000, $preview->totalCost);
        $this->assertSame('2026-12-21', $preview->endDate->toDateString());
        $this->assertSame(0, Rental::count());
    }

    private function expectRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Sewa seharusnya ditolak');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
}
