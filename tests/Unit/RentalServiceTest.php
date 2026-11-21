<?php

namespace Tests\Unit;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
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

    // ---------------------------------------------------------------- perpanjangan (BR-07)

    private function ongoingRental(string $start = '2026-11-18', string $end = '2026-11-24'): Rental
    {
        return Rental::factory()->for($this->carGarage('C950'))->between($start, $end)->create();
    }

    /** TC-066: hanya sewa yang belum berakhir (berjalan dan akan datang), urut tanggal selesai. */
    public function test_tc066_active_rentals(): void
    {
        $ongoing = Rental::factory()->for($this->carGarage('C901'))->between('2026-11-18', '2026-11-25')->create();
        $upcoming = Rental::factory()->for($this->carGarage('C902'))->between('2026-11-28', '2026-11-30')->create();
        Rental::factory()->for($this->carGarage('C903'))->between('2026-11-10', '2026-11-21')->create();

        $active = $this->service->activeRentals(today());

        $this->assertSame([$ongoing->id, $upcoming->id], $active->pluck('id')->all());
    }

    /** TC-067: perpanjangan valid membuat catatan perpanjangan dan mengubah tanggal selesai. */
    public function test_tc067_extend_rental(): void
    {
        $rental = $this->ongoingRental();
        $admin = User::factory()->admin()->create();

        $extension = $this->service->extendRental($rental, Carbon::parse('2026-11-27'), $admin, $this->proof());

        $this->assertSame('2026-11-24', $extension->old_end_date->toDateString());
        $this->assertSame('2026-11-27', $extension->new_end_date->toDateString());
        $this->assertSame(0, $extension->extra_months);
        $this->assertSame(3, $extension->extra_days);
        $this->assertEquals(300000, $extension->extra_cost);
        $this->assertTrue($extension->processor->is($admin));
        $this->assertSame('2026-11-27', $rental->refresh()->end_date->toDateString());
        Storage::disk('public')->assertExists($extension->payment_proof_path);
    }

    /** TC-015 lewat RentalService: 21 Des 2026 → 24 Jan 2027 = 1 bulan + 3 hari = Rp2.800.000. */
    public function test_preview_extension_month_and_days(): void
    {
        $rental = $this->ongoingRental('2026-11-21', '2026-12-21');

        $preview = $this->service->previewExtension($rental, Carbon::parse('2027-01-24'));

        $this->assertSame(1, $preview->months);
        $this->assertSame(3, $preview->days);
        $this->assertEquals(2800000, $preview->cost);
    }

    /** TC-068: tanggal baru sama dengan atau sebelum tanggal lama ditolak. */
    public function test_tc068_new_end_date_must_be_later(): void
    {
        $rental = $this->ongoingRental();

        foreach (['2026-11-24', '2026-11-22'] as $newEnd) {
            $this->expectRejected(fn () => $this->service->previewExtension($rental, Carbon::parse($newEnd)));
        }
    }

    /** TC-069: perpanjangan tanpa batas maksimum hari. */
    public function test_tc069_long_extension_is_accepted(): void
    {
        $rental = $this->ongoingRental();

        $extension = $this->service->extendRental(
            $rental, Carbon::parse('2026-11-24')->addDays(200), User::factory()->admin()->create(), $this->proof(),
        );

        $this->assertGreaterThan(0, $extension->extra_cost);
        $this->assertSame('2027-06-12', $rental->refresh()->end_date->toDateString());
    }

    /** TC-070: kurang bayar memakai harga snapshot, bukan harga garasi terbaru. */
    public function test_tc070_extension_uses_price_snapshot(): void
    {
        $rental = $this->ongoingRental();
        $rental->garage->update(['daily_price' => 999999]);

        $this->assertEquals(300000, $this->service->previewExtension($rental, Carbon::parse('2026-11-27'))->cost);
    }

    /** TC-072: sewa yang sudah berakhir tidak bisa diperpanjang. */
    public function test_tc072_ended_rental_cannot_be_extended(): void
    {
        $rental = $this->ongoingRental('2026-11-10', '2026-11-21');

        $this->expectRejected(fn () => $this->service->previewExtension($rental, Carbon::parse('2026-11-25')));
        $this->expectRejected(fn () => $this->service->extendRental(
            $rental, Carbon::parse('2026-11-25'), User::factory()->admin()->create(), $this->proof(),
        ));

        $this->assertSame(0, RentalExtension::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** TC-073: perpanjangan yang menabrak sewa berikutnya di garasi yang sama ditolak; tepat bersambung diterima. */
    public function test_tc073_extension_cannot_overlap_next_rental(): void
    {
        $rental = $this->ongoingRental();
        Rental::factory()->for($rental->garage)->between('2026-11-26', '2026-11-30')->create();
        $admin = User::factory()->admin()->create();

        $this->expectRejected(fn () => $this->service->extendRental($rental, Carbon::parse('2026-11-27'), $admin, $this->proof()));
        $this->assertSame('2026-11-24', $rental->refresh()->end_date->toDateString());

        $this->service->extendRental($rental, Carbon::parse('2026-11-26'), $admin, $this->proof());
        $this->assertSame('2026-11-26', $rental->refresh()->end_date->toDateString());
    }

    /** Perpanjangan ditolak bila kendaraannya sudah punya sewa lain pada tambahan tanggal tersebut (BR-10). */
    public function test_extension_cannot_overlap_same_vehicle_rental(): void
    {
        $rental = $this->ongoingRental();
        Rental::factory()->for($this->carGarage('C951'))->for($rental->vehicle)->for($rental->user)
            ->between('2026-11-25', '2026-11-28')->create();

        $this->expectRejected(fn () => $this->service->previewExtension($rental, Carbon::parse('2026-11-27')));
    }

    /** TC-081: sewa baru menunggu verifikasi pembayaran oleh admin (BR-12.1). */
    public function test_tc081_new_rental_waits_for_payment_verification(): void
    {
        $rental = $this->service->createRental($this->renter, $this->input($this->carGarage()), $this->proof());

        $this->assertSame(PaymentStatus::PENDING, $rental->payment_status);
        $this->assertNull($rental->payment_verified_at);
        $this->assertNull($rental->payment_verified_by);
    }

    /** TC-082: admin menerima pembayaran, waktu dan admin pemeriksa dicatat (BR-12.2). */
    public function test_tc082_approve_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $rental = Rental::factory()->pendingPayment()->for($this->carGarage())->create();

        $this->service->approvePayment($rental, $admin);

        $rental->refresh();
        $this->assertSame(PaymentStatus::APPROVED, $rental->payment_status);
        $this->assertTrue($rental->payment_verified_at->equalTo(now()));
        $this->assertSame($admin->id, $rental->payment_verified_by);
    }

    /** TC-083: pembayaran ditolak melepas garasi bila tidak ada sewa lain yang belum berakhir (BR-12.4). */
    public function test_tc083_reject_payment_releases_garage(): void
    {
        $admin = User::factory()->admin()->create();
        $garage = Garage::factory()->ofType(GarageType::CAR)->used()->create(['code' => 'C901']);
        $rental = Rental::factory()->pendingPayment()->for($garage)->create();

        $this->service->rejectPayment($rental, $admin);

        $this->assertSame(PaymentStatus::REJECTED, $rental->refresh()->payment_status);
        $this->assertSame($admin->id, $rental->payment_verified_by);
        $this->assertSame(GarageStatus::AVAILABLE, $garage->refresh()->status);
    }

    /** Garasi tetap used bila masih ada sewa lain yang belum berakhir. */
    public function test_reject_payment_keeps_garage_used_when_other_rental_exists(): void
    {
        $garage = Garage::factory()->ofType(GarageType::CAR)->used()->create(['code' => 'C901']);
        Rental::factory()->for($garage)->between('2026-11-21', '2026-11-24')->create();
        $pending = Rental::factory()->pendingPayment()->for($garage)->between('2026-11-28', '2026-11-30')->create();

        $this->service->rejectPayment($pending, User::factory()->admin()->create());

        $this->assertSame(GarageStatus::USED, $garage->refresh()->status);
    }

    /** TC-084: sewa yang sudah diproses tidak dapat diproses lagi (BR-12.2). */
    public function test_tc084_processed_payment_cannot_be_processed_again(): void
    {
        $admin = User::factory()->admin()->create();
        $approved = Rental::factory()->for($this->carGarage('C901'))->create();
        $rejected = Rental::factory()->rejectedPayment()->for($this->carGarage('C902'))->create();

        $this->expectRejected(fn () => $this->service->approvePayment($approved, $admin));
        $this->expectRejected(fn () => $this->service->rejectPayment($approved, $admin));
        $this->expectRejected(fn () => $this->service->approvePayment($rejected, $admin));
        $this->assertSame(PaymentStatus::APPROVED, $approved->refresh()->payment_status);
        $this->assertSame(PaymentStatus::REJECTED, $rejected->refresh()->payment_status);
    }

    /** TC-085: sewa yang menunggu verifikasi tetap menahan garasi dan plat kendaraan (BR-12.3). */
    public function test_tc085_pending_rental_holds_garage_and_plate(): void
    {
        $garage = $this->carGarage('C901');
        $vehicle = Vehicle::factory()->create(['plate_number' => 'L 1234 AB']);
        Rental::factory()->pendingPayment()->for($garage)->for($vehicle)->for($vehicle->user)
            ->between('2026-11-21', '2026-11-24')->create();

        $this->expectRejected(fn () => $this->service->createRental(
            $this->renter, $this->input($garage, ['plate_number' => 'L 9999 ZZ']), $this->proof()));
        $this->expectRejected(fn () => $this->service->createRental(
            $this->renter, $this->input($this->carGarage('C902')), $this->proof()));
    }

    /** TC-086: sewa yang pembayarannya ditolak tidak lagi menahan garasi dan plat (BR-12.4). */
    public function test_tc086_rejected_rental_frees_garage_and_plate(): void
    {
        $garage = $this->carGarage('C901');
        $vehicle = Vehicle::factory()->create(['plate_number' => 'L 1234 AB']);
        Rental::factory()->rejectedPayment()->for($garage)->for($vehicle)->for($vehicle->user)
            ->between('2026-11-21', '2026-11-24')->create();

        $rental = $this->service->createRental($this->renter, $this->input($garage), $this->proof());

        $this->assertSame('C901', $rental->garage->code);
        $this->assertSame('L 1234 AB', $rental->vehicle->plate_number);
    }

    /** TC-087: hanya sewa yang pembayarannya diterima yang bisa diperpanjang (BR-12.5). */
    public function test_tc087_only_approved_rentals_can_be_extended(): void
    {
        $approved = Rental::factory()->for($this->carGarage('C901'))->between('2026-11-18', '2026-11-25')->create();
        $pending = Rental::factory()->pendingPayment()->for($this->carGarage('C902'))->between('2026-11-18', '2026-11-25')->create();
        Rental::factory()->rejectedPayment()->for($this->carGarage('C903'))->between('2026-11-18', '2026-11-25')->create();

        $this->assertSame([$approved->id], $this->service->activeRentals(today())->pluck('id')->all());
        $this->expectRejected(fn () => $this->service->previewExtension($pending, Carbon::parse('2026-11-27')));
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
