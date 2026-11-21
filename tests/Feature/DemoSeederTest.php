<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Data demo untuk presentasi: akun, garasi, sewa berjalan/akan datang/selesai, dan perpanjangan.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_accounts_cover_every_verification_status(): void
    {
        $this->assertSame(1, User::where('role', UserRole::ADMIN)->count());

        $renters = User::where('role', UserRole::PENYEWA)->get()->groupBy(fn (User $user) => $user->verification_status->value);
        $this->assertCount(2, $renters[VerificationStatus::APPROVED->value]);
        $this->assertCount(1, $renters[VerificationStatus::PENDING->value]);
        $this->assertCount(1, $renters[VerificationStatus::REJECTED->value]);

        $budi = User::where('email', 'budi@garasi.test')->firstOrFail();
        $this->assertTrue(Hash::check(config('garasi.demo.password'), $budi->password));
    }

    public function test_demo_garages_use_generated_codes(): void
    {
        $codes = Garage::orderBy('code')->pluck('code')->all();

        $this->assertSame(
            ['C001', 'C002', 'C003', 'C004', 'C005', 'E001', 'E002', 'E003', 'S001', 'S002', 'S003', 'S004', 'S005'],
            $codes,
        );
        $this->assertEquals(2500, Garage::where('code', 'E001')->value('kwh_price'));
    }

    public function test_demo_rentals_cover_past_ongoing_upcoming_and_extension(): void
    {
        $today = today();

        $this->assertTrue(Rental::whereDate('end_date', '<=', $today)->exists(), 'ada sewa yang sudah selesai');
        $this->assertTrue(Rental::activeOn($today)->exists(), 'ada sewa yang sedang berjalan');
        $this->assertTrue(Rental::whereDate('start_date', '>', $today)->exists(), 'ada sewa yang akan datang');
        $this->assertTrue(Rental::where('charging_fee', '>', 0)->exists(), 'ada sewa mobil listrik dengan biaya isi');
        $this->assertSame(1, RentalExtension::count());

        foreach (Rental::all() as $rental) {
            Storage::disk('public')->assertExists($rental->payment_proof_path);
        }
    }

    public function test_demo_data_is_consistent(): void
    {
        foreach (Garage::with('rentals')->get() as $garage) {
            $hasUnfinishedRental = $garage->rentals->contains(fn (Rental $rental) => $rental->end_date->gt(today()));
            $this->assertSame(
                $hasUnfinishedRental ? GarageStatus::USED : GarageStatus::AVAILABLE,
                $garage->status,
                "status {$garage->code}",
            );

            foreach ($garage->rentals as $rental) {
                $this->assertFalse(
                    Rental::where('garage_id', $garage->id)->whereKeyNot($rental->id)
                        ->overlapping($rental->start_date, $rental->end_date)->exists(),
                    "sewa bertabrakan di {$garage->code}",
                );
                $this->assertTrue($rental->garage->garage_type->acceptsVehicle($rental->vehicle->type));
            }
        }

        $this->assertFalse(
            Rental::whereHas('user', fn ($user) => $user->where('verification_status', '!=', VerificationStatus::APPROVED))->exists(),
            'hanya penyewa yang disetujui yang punya sewa',
        );
    }
}
