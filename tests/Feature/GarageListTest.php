<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Enums\GarageStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar garasi untuk admin dengan filter tipe dan status (REQ-GAR-02).
 */
class GarageListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();

        Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S901', 'daily_price' => 5000]);
        $rented = Garage::factory()->ofType(GarageType::CAR)->used()->create(['code' => 'C901', 'daily_price' => 100000]);
        Rental::factory()->for($rented)->between(today()->toDateString(), today()->addDays(3)->toDateString())->create();
        Garage::factory()->ofType(GarageType::ELECTRIC_CAR)->create(['code' => 'E901', 'daily_price' => 150000]);
    }

    public function test_lists_all_garages_with_price_and_status(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.garages.index'))
            ->assertOk()
            ->assertSeeInOrder(['C901', 'E901', 'S901'])
            ->assertSee('Rp100.000')
            ->assertSee('Rp2.500')
            ->assertSee('Disewa')
            ->assertSee('Tersedia');
    }

    public function test_filters_by_type_and_status(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.garages.index', ['type' => 'electric_car']))
            ->assertViewHas('garages', fn ($garages) => $garages->pluck('code')->all() === ['E901']);

        $this->actingAs($this->admin)
            ->get(route('admin.garages.index', ['status' => 'used']))
            ->assertViewHas('garages', fn ($garages) => $garages->pluck('code')->all() === ['C901']);
    }

    public function test_invalid_filter_is_ignored(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.garages.index', ['type' => 'kapal']))
            ->assertOk()
            ->assertViewHas('garages', fn ($garages) => $garages->count() === 3);
    }

    /** Garasi yang masa sewanya sudah habis langsung tampil tersedia tanpa menunggu penjadwal harian. */
    public function test_opening_list_releases_expired_garages(): void
    {
        $garage = Garage::factory()->used()->create(['code' => 'C950']);
        Rental::factory()->for($garage)->between(today()->subDays(5)->toDateString(), today()->toDateString())->create();

        $this->actingAs($this->admin)
            ->get(route('admin.garages.index', ['status' => 'available']))
            ->assertViewHas('garages', fn ($garages) => $garages->pluck('code')->contains('C950'));

        $this->assertSame(GarageStatus::AVAILABLE, $garage->refresh()->status);
    }

    public function test_renter_cannot_open_garage_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.garages.index'))
            ->assertForbidden();
    }
}
