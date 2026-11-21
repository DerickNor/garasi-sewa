<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageSequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Form buat garasi berkelompok oleh admin (REQ-GAR-01, TC-048..TC-052).
 */
class GarageCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function submit(array $input, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.garages.store'), $input);
    }

    public function test_create_form_shows_next_codes(): void
    {
        GarageSequence::whereKey(GarageType::CAR->value)->update(['last_number' => 9]);

        $this->actingAs($this->admin)
            ->get(route('admin.garages.create'))
            ->assertOk()
            ->assertSee('S001')
            ->assertSee('C010')
            ->assertSee('E001');
    }

    /** TC-048: input valid membuat garasi berurutan dengan harga tersimpan di tiap garasi. */
    public function test_tc048_valid_input_creates_garages(): void
    {
        $this->submit(['garage_type' => 'car', 'quantity' => 3, 'daily_price' => 100000])
            ->assertRedirect(route('admin.garages.index', ['type' => 'car']))
            ->assertSessionHas('status', fn (string $message) => str_contains($message, 'C001') && str_contains($message, 'C003'));

        $garages = Garage::orderBy('code')->get();
        $this->assertSame(['C001', 'C002', 'C003'], $garages->pluck('code')->all());
        foreach ($garages as $garage) {
            $this->assertSame(GarageType::CAR, $garage->garage_type);
            $this->assertEquals(100000, $garage->daily_price);
            $this->assertNull($garage->kwh_price);
            $this->assertSame(GarageStatus::AVAILABLE, $garage->status);
        }
    }

    public function test_tc048_electric_garages_store_kwh_price(): void
    {
        $this->submit(['garage_type' => 'electric_car', 'quantity' => 2, 'daily_price' => 150000, 'kwh_price' => 2500])
            ->assertSessionHasNoErrors();

        $this->assertSame(['E001', 'E002'], Garage::orderBy('code')->pluck('code')->all());
        $this->assertEquals(2500, Garage::first()->kwh_price);
    }

    /** TC-049: harga kWh wajib untuk tipe E dan ditolak untuk tipe lain. */
    public function test_tc049_kwh_price_rules(): void
    {
        $this->submit(['garage_type' => 'electric_car', 'quantity' => 1, 'daily_price' => 150000])
            ->assertSessionHasErrors('kwh_price');
        $this->submit(['garage_type' => 'car', 'quantity' => 1, 'daily_price' => 100000, 'kwh_price' => 2500])
            ->assertSessionHasErrors('kwh_price');
        $this->submit(['garage_type' => 'bicycle', 'quantity' => 1, 'daily_price' => 5000, 'kwh_price' => 2500])
            ->assertSessionHasErrors('kwh_price');

        $this->assertSame(0, Garage::count());
    }

    /** TC-050: jumlah 0 dan 1000 ditolak. */
    public function test_tc050_quantity_out_of_range_is_rejected(): void
    {
        foreach ([0, 1000, 'abc'] as $quantity) {
            $this->submit(['garage_type' => 'car', 'quantity' => $quantity, 'daily_price' => 100000])
                ->assertSessionHasErrors('quantity');
        }

        $this->assertSame(0, Garage::count());
    }

    /** TC-051: jumlah melebihi sisa kode tipe tersebut ditolak dan tidak ada garasi tersimpan. */
    public function test_tc051_quantity_beyond_remaining_codes_is_rejected(): void
    {
        GarageSequence::whereKey(GarageType::CAR->value)->update(['last_number' => 998]);

        $this->submit(['garage_type' => 'car', 'quantity' => 3, 'daily_price' => 100000])
            ->assertSessionHasErrors(['quantity' => 'Sisa kode garasi Mobil hanya 1.']);

        $this->assertSame(0, Garage::count());
        $this->assertSame(998, GarageSequence::find(GarageType::CAR->value)->last_number);
    }

    public function test_required_fields_and_price_validation(): void
    {
        $this->submit([])->assertSessionHasErrors(['garage_type', 'quantity', 'daily_price']);
        $this->submit(['garage_type' => 'kapal', 'quantity' => 1, 'daily_price' => 0])
            ->assertSessionHasErrors(['garage_type', 'daily_price']);
    }

    /** TC-052: penyewa tidak bisa membuat garasi. */
    public function test_tc052_renter_cannot_create_garages(): void
    {
        $this->submit(['garage_type' => 'car', 'quantity' => 3, 'daily_price' => 100000], User::factory()->create())
            ->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('admin.garages.create'))->assertForbidden();
        $this->assertSame(0, Garage::count());
    }
}
