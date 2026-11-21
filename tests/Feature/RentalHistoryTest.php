<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat sewa milik penyewa (REQ-RENT-09).
 */
class RentalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_renter_sees_only_own_rentals_with_period_and_total(): void
    {
        $renter = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $renter->id, 'plate_number' => 'L 1234 AB']);
        $garage = Garage::factory()->ofType(GarageType::CAR)->create(['code' => 'C901']);
        $rental = Rental::factory()->for($renter)->for($garage)->for($vehicle)->between('2027-01-02', '2027-01-05')->create();
        RentalExtension::create([
            'rental_id' => $rental->id, 'processed_by' => User::factory()->admin()->create()->id,
            'old_end_date' => '2027-01-05', 'new_end_date' => '2027-01-07', 'extra_months' => 0, 'extra_days' => 2,
            'extra_cost' => 200000, 'payment_proof_path' => 'proofs/x.png',
        ]);
        $rental->update(['end_date' => '2027-01-07']);

        $other = Rental::factory()->for(Garage::factory()->create(['code' => 'C902']))->create();

        $this->actingAs($renter)
            ->get(route('renter.rentals.index'))
            ->assertOk()
            ->assertSee('C901')
            ->assertSee('L 1234 AB')
            ->assertSee('2 Jan 2027')
            ->assertSee('6 Jan 2027')
            ->assertSee('7 Jan 2027')
            ->assertSee('Rp300.000')
            ->assertSee('Rp500.000')
            ->assertSee('Harian, 5 hari')
            ->assertDontSee($other->garage->code);
    }

    public function test_pending_renter_can_open_empty_history(): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->get(route('renter.rentals.index'))
            ->assertOk()
            ->assertSee('Belum ada sewa');
    }
}
