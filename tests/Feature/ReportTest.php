<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Halaman laporan sewa admin (REQ-REP-01..04, TC-079, TC-080).
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $renter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 08:00');

        $this->admin = User::factory()->admin()->create();
        $this->renter = User::factory()->create(['name' => 'Budi Penyewa']);

        Rental::factory()->for($this->renter)->for(Garage::factory()->ofType(GarageType::CAR))
            ->between('2026-11-03', '2026-11-06')->create(['total_cost' => 300000]);
        Rental::factory()->for($this->renter)->for(Garage::factory()->ofType(GarageType::BICYCLE))
            ->between('2026-11-21', '2026-11-22')->create(['total_cost' => 5000]);
    }

    public function test_reports_index_redirects_to_monthly_report(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertRedirect(route('admin.reports.monthly'));
    }

    /** TC-079: laporan per bulan memakai tahun berjalan dan total sama dengan jumlah baris. */
    public function test_tc079_monthly_report_shows_rows_and_total(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly'))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 12)
            ->assertViewHas('totalRentals', 2)
            ->assertViewHas('totalRevenue', 305000.0)
            ->assertSeeInOrder(['Januari', 'November', 'Desember', 'Total', 'Rp305.000']);
    }

    public function test_daily_report_has_one_row_per_calendar_day(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.daily', ['month' => 2, 'year' => 2028]))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 29);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.daily', ['month' => 11, 'year' => 2026]))
            ->assertViewHas('rows', fn ($rows) => $rows[2]['rentals'] === 1 && $rows[20]['rentals'] === 1)
            ->assertSee('Rp300.000');
    }

    public function test_report_by_garage_type(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.by-type'))
            ->assertOk()
            ->assertSeeInOrder(['Sepeda', 'Mobil', 'Mobil Listrik'])
            ->assertViewHas('totalRevenue', 305000.0);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.by-type', ['from' => '2026-11-10', 'to' => '2026-11-30']))
            ->assertViewHas('totalRentals', 1);
    }

    public function test_report_by_renter(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.by-renter'))
            ->assertOk()
            ->assertSee('Budi Penyewa')
            ->assertSee($this->renter->email)
            ->assertViewHas('totalRentals', 2);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.daily', ['month' => 13, 'year' => 2026]))
            ->assertSessionHasErrors('month');
        $this->actingAs($this->admin)
            ->get(route('admin.reports.by-type', ['from' => '2026-11-30', 'to' => '2026-11-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_admin_dashboard_shows_active_rentals_and_month_revenue(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('activeRentals', 1)
            ->assertViewHas('monthRevenue', 305000.0)
            ->assertSee('Rp305.000');
    }

    /** TC-080: hanya admin yang bisa membuka laporan. */
    public function test_tc080_only_admin_can_open_reports(): void
    {
        foreach (['admin.reports.monthly', 'admin.reports.daily', 'admin.reports.by-type', 'admin.reports.by-renter'] as $route) {
            $this->actingAs($this->renter)->get(route($route))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('admin.reports.monthly'))->assertRedirect(route('login'));
    }
}
