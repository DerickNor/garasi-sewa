<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sewa baru lewat HTTP: validasi form, endpoint garasi tersedia, preview biaya, simpan (REQ-RENT-01..08).
 */
class RentalCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $renter;

    private Garage $carGarage;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 08:00');
        Storage::fake('public');

        $this->renter = User::factory()->create();
        $this->carGarage = Garage::factory()->ofType(GarageType::CAR)->create(['code' => 'C901', 'daily_price' => 100000]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'vehicle_type' => 'car',
            'plate_number' => 'L 1234 AB',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'rental_type' => 'daily',
            'days' => 3,
            'start_date' => '2026-11-21',
            'garage_type' => 'car',
            'garage_id' => $this->carGarage->id,
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function store(array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->renter)->post(route('renter.rentals.store'), $this->input($overrides));
    }

    public function test_valid_rental_is_saved_and_redirects_to_history(): void
    {
        $this->store()
            ->assertRedirect(route('renter.rentals.index'))
            ->assertSessionHas('status', fn (string $message) => str_contains($message, 'menunggu verifikasi pembayaran'));

        $rental = Rental::sole();
        $this->assertSame(PaymentStatus::PENDING, $rental->payment_status);
        $this->assertTrue($rental->user->is($this->renter));
        $this->assertEquals(300000, $rental->total_cost);
        $this->assertSame(GarageStatus::USED, $this->carGarage->refresh()->status);
    }

    /** TC-055: jumlah hari melebihi maksimum bulan mulai ditolak. */
    public function test_tc055_days_above_month_limit_are_rejected(): void
    {
        $this->store(['days' => 30])->assertSessionHasErrors('days');
        $this->store(['days' => 0])->assertSessionHasErrors('days');
        $this->store(['days' => 29])->assertSessionHasNoErrors();
    }

    /** TC-056: tipe garasi yang tidak cocok dengan kendaraan ditolak di server. */
    public function test_tc056_incompatible_garage_type_is_rejected(): void
    {
        $bicycleGarage = Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S901']);

        $this->store(['garage_type' => 'bicycle', 'garage_id' => $bicycleGarage->id])
            ->assertSessionHasErrors('garage_type');
        $this->store(['garage_type' => 'car', 'garage_id' => $bicycleGarage->id])
            ->assertSessionHasErrors('garage_id');

        $this->assertSame(0, Rental::count());
    }

    /** Mobil listrik boleh di garasi mobil (BR-01); baterai tidak diisi sehingga tanpa biaya isi listrik. */
    public function test_electric_car_may_use_car_garage_without_charging(): void
    {
        $this->store([
            'vehicle_type' => 'electric_car', 'battery_capacity_kwh' => 60, 'battery_start_kwh' => 20,
            'garage_type' => 'car', 'garage_id' => $this->carGarage->id,
        ])->assertRedirect(route('renter.rentals.index'));

        $rental = Rental::sole();
        $this->assertEquals(0, $rental->charging_fee);
        $this->assertEquals(300000, $rental->total_cost);
    }

    /** Tipe garasi berupa pilihan yang disesuaikan dengan kendaraan (mobil listrik: mobil atau mobil listrik). */
    public function test_rental_form_offers_garage_type_choices_per_vehicle(): void
    {
        $this->actingAs($this->renter)
            ->get(route('renter.rentals.create'))
            ->assertSee('<select id="garage_type" name="garage_type"', false)
            ->assertViewHas('allowedGarageTypes', fn ($allowed) => $allowed['electric_car'] === ['car', 'electric_car']
                && $allowed['car'] === ['car']
                && $allowed['bicycle'] === ['bicycle']);
    }

    /** TC-057: garasi yang sudah tidak tersedia ditolak dan tidak ada data tersimpan. */
    public function test_tc057_unavailable_garage_is_rejected(): void
    {
        Rental::factory()->for($this->carGarage)->between('2026-11-22', '2026-11-25')->create();

        $this->store()->assertSessionHas('error');

        $this->assertSame(1, Rental::count());
    }

    /** TC-058: bukti bukan gambar atau lebih dari 2 MB ditolak. */
    public function test_tc058_invalid_payment_proof_is_rejected(): void
    {
        $this->store(['payment_proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('payment_proof');
        $this->store(['payment_proof' => UploadedFile::fake()->image('bukti.jpg')->size(2049)])
            ->assertSessionHasErrors('payment_proof');
        $this->store(['payment_proof' => null])->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, Rental::count());
    }

    /** TC-059: total biaya yang dikirim browser diabaikan. */
    public function test_tc059_manipulated_total_is_ignored(): void
    {
        $this->store(['total_cost' => 1, 'rental_fee' => 1]);

        $this->assertEquals(300000, Rental::sole()->total_cost);
    }

    /** TC-060: plat wajib untuk motor/mobil/mobil listrik, diabaikan untuk sepeda. */
    public function test_tc060_plate_rules(): void
    {
        $this->store(['plate_number' => ''])->assertSessionHasErrors('plate_number');
        $this->store(['plate_number' => 'BUKAN PLAT 123'])->assertSessionHasErrors('plate_number');

        $bicycleGarage = Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S901']);
        $this->store([
            'vehicle_type' => 'bicycle', 'plate_number' => 'L 1 A', 'brand' => 'Polygon', 'model' => 'Xtrada',
            'garage_type' => 'bicycle', 'garage_id' => $bicycleGarage->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Rental::sole()->vehicle->plate_number);
    }

    /** TC-061: baterai awal melebihi kapasitas ditolak; data baterai wajib untuk mobil listrik. */
    public function test_tc061_battery_rules(): void
    {
        $electric = ['vehicle_type' => 'electric_car', 'battery_capacity_kwh' => 60];

        $this->store($electric + ['battery_start_kwh' => 61])->assertSessionHasErrors('battery_start_kwh');
        $this->store($electric + ['battery_start_kwh' => -1])->assertSessionHasErrors('battery_start_kwh');
        $this->store(['vehicle_type' => 'electric_car'])->assertSessionHasErrors(['battery_capacity_kwh', 'battery_start_kwh']);

        $this->assertSame(0, Rental::count());
    }

    /** Kapasitas baterai tidak dibatasi aturan bisnis; biaya isi listrik besar tetap tersimpan. */
    public function test_large_battery_capacity_is_accepted(): void
    {
        $electricGarage = Garage::factory()->ofType(GarageType::ELECTRIC_CAR)->create(['code' => 'E901', 'daily_price' => 150000]);

        $this->store([
            'vehicle_type' => 'electric_car', 'battery_capacity_kwh' => 5000, 'battery_start_kwh' => 0,
            'garage_type' => 'electric_car', 'garage_id' => $electricGarage->id,
        ])->assertSessionHasNoErrors();

        $rental = Rental::sole();
        $this->assertEquals(5000, $rental->vehicle->battery_capacity_kwh);
        $this->assertEquals(5000 * 2500, $rental->charging_fee);

        $this->store(['vehicle_type' => 'electric_car', 'battery_capacity_kwh' => 0, 'battery_start_kwh' => 0])
            ->assertSessionHasErrors('battery_capacity_kwh');
    }

    /** TC-064: tanggal mulai hari ini s.d. 30 hari ke depan. */
    public function test_tc064_start_date_range(): void
    {
        $this->store(['start_date' => '2026-11-20'])->assertSessionHasErrors('start_date');
        $this->store(['start_date' => '2026-12-22'])->assertSessionHasErrors('start_date');
        $this->store(['start_date' => '2026-12-21', 'days' => 3])->assertSessionHasNoErrors();

        $this->assertSame('2026-12-24', Rental::sole()->end_date->toDateString());
    }

    /** TC-047: penyewa pending tidak bisa menyimpan sewa. */
    public function test_tc047_pending_renter_cannot_save_rental(): void
    {
        $this->store([], User::factory()->pending()->create())
            ->assertRedirect(route('renter.dashboard'))
            ->assertSessionHas('error');

        $this->assertSame(0, Rental::count());
    }

    public function test_approved_renter_can_open_rental_form(): void
    {
        $this->actingAs($this->renter)
            ->get(route('renter.rentals.create'))
            ->assertOk()
            ->assertViewHas('defaultGarageTypes', fn ($types) => $types['electric_car'] === 'electric_car' && $types['motorcycle'] === 'bicycle')
            ->assertSee('Sewa Garasi Baru')
            ->assertSee('name="payment_proof"', false);
    }

    /** TC-040: admin tidak bisa membuka form sewa penyewa. */
    public function test_tc040_admin_cannot_open_rental_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('renter.rentals.create'))
            ->assertForbidden();
    }

    public function test_available_garages_endpoint_returns_period_and_free_garages(): void
    {
        $booked = Garage::factory()->ofType(GarageType::CAR)->create(['code' => 'C902']);
        Rental::factory()->for($booked)->between('2026-12-03', '2026-12-06')->create();
        Garage::factory()->ofType(GarageType::ELECTRIC_CAR)->create(['code' => 'E901']);

        $this->actingAs($this->renter)
            ->getJson(route('renter.garages.available', [
                'garage_type' => 'car', 'rental_type' => 'daily', 'days' => 3, 'start_date' => '2026-12-02',
            ]))
            ->assertOk()
            ->assertJsonPath('end_date', '2026-12-05')
            ->assertJsonPath('last_day', '2026-12-04')
            ->assertJsonPath('duration_days', 3)
            ->assertJsonPath('max_daily_days', 30)
            ->assertJsonPath('garages.0.code', 'C901')
            ->assertJsonCount(1, 'garages');
    }

    /** Tanggal selesai sudah bisa ditampilkan sebelum tipe garasi dipilih. */
    public function test_available_garages_endpoint_returns_period_without_garage_type(): void
    {
        $this->actingAs($this->renter)
            ->getJson(route('renter.garages.available', ['rental_type' => 'monthly', 'start_date' => '2026-11-21']))
            ->assertOk()
            ->assertJsonPath('end_date', '2026-12-21')
            ->assertJsonPath('duration_days', 30)
            ->assertJsonCount(0, 'garages');
    }

    public function test_rental_form_shows_start_and_end_date_fields(): void
    {
        $this->actingAs($this->renter)
            ->get(route('renter.rentals.create'))
            ->assertSee('Tanggal mulai')
            ->assertSee('Tanggal selesai')
            ->assertSee('id="end_date"', false);
    }

    public function test_available_garages_endpoint_validates_input(): void
    {
        $this->actingAs($this->renter)
            ->getJson(route('renter.garages.available', ['garage_type' => 'car', 'rental_type' => 'daily', 'days' => 40, 'start_date' => '2026-11-21']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('days');
    }

    public function test_preview_endpoint_returns_cost_breakdown(): void
    {
        $electricGarage = Garage::factory()->ofType(GarageType::ELECTRIC_CAR)->create(['code' => 'E901', 'daily_price' => 200000]);

        $this->actingAs($this->renter)
            ->postJson(route('renter.rentals.preview'), $this->input([
                'vehicle_type' => 'electric_car', 'battery_capacity_kwh' => 60, 'battery_start_kwh' => 22.5,
                'garage_type' => 'electric_car', 'garage_id' => $electricGarage->id, 'payment_proof' => null,
            ]))
            ->assertOk()
            ->assertJsonPath('rental_fee', 600000)
            ->assertJsonPath('charging_fee', 93750)
            ->assertJsonPath('total_cost', 693750)
            ->assertJsonPath('end_date', '2026-11-24');

        $this->assertSame(0, Rental::count());
    }
}
