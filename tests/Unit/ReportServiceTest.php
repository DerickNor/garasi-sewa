<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Unit test laporan sewa (REQ-REP-01..04, ASM-09, TC-074..TC-079).
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reports = app(ReportService::class);
    }

    private function rental(string $start, float $total, GarageType $type = GarageType::CAR, ?User $renter = null): Rental
    {
        $factory = Rental::factory()
            ->for(Garage::factory()->ofType($type))
            ->between($start, Carbon::parse($start)->addDays(2)->toDateString());

        if ($renter !== null) {
            $factory = $factory->for($renter);
        }

        return $factory->create(['total_cost' => $total]);
    }

    private function extend(Rental $rental, float $cost): void
    {
        RentalExtension::create([
            'rental_id' => $rental->id,
            'processed_by' => User::factory()->admin()->create()->id,
            'old_end_date' => $rental->end_date,
            'new_end_date' => $rental->end_date->copy()->addDay(),
            'extra_months' => 0,
            'extra_days' => 1,
            'extra_cost' => $cost,
            'payment_proof_path' => 'proofs/x.png',
        ]);
    }

    /**
     * @param  iterable<array{rentals: int, revenue: float}>  $rows
     */
    private function totals(iterable $rows): array
    {
        $rentals = 0;
        $revenue = 0.0;
        foreach ($rows as $row) {
            $rentals += $row['rentals'];
            $revenue += $row['revenue'];
        }

        return [$rentals, $revenue];
    }

    /** TC-074: per bulan menghasilkan 12 baris, bulan tanpa sewa bernilai 0, tahun lain tidak ikut. */
    public function test_tc074_monthly_report(): void
    {
        $this->rental('2026-01-15', 300000);
        $this->rental('2026-01-31', 200000);
        $this->rental('2026-11-21', 500000);
        $this->rental('2025-11-21', 999999);

        $rows = $this->reports->monthlyInYear(2026);

        $this->assertCount(12, $rows);
        $this->assertSame('Januari', $rows[0]['label']);
        $this->assertSame(2, $rows[0]['rentals']);
        $this->assertEquals(500000, $rows[0]['revenue']);
        $this->assertSame(0, $rows[1]['rentals']);
        $this->assertEquals(0, $rows[1]['revenue']);
        $this->assertSame(1, $rows[10]['rentals']);
        $this->assertSame([3, 1000000.0], $this->totals($rows));
    }

    /** TC-075: per hari, jumlah baris mengikuti jumlah hari kalender bulan itu. */
    public function test_tc075_daily_report(): void
    {
        $this->rental('2026-02-03', 100000);
        $this->rental('2026-02-03', 150000);
        $this->rental('2026-02-28', 200000);
        $this->rental('2026-03-01', 999999);

        $february = $this->reports->dailyInMonth(2, 2026);

        $this->assertCount(28, $february);
        $this->assertCount(29, $this->reports->dailyInMonth(2, 2028));
        $this->assertCount(31, $this->reports->dailyInMonth(12, 2026));
        $this->assertSame(2, $february[2]['rentals']);
        $this->assertEquals(250000, $february[2]['revenue']);
        $this->assertSame(1, $february[27]['rentals']);
        $this->assertSame([3, 450000.0], $this->totals($february));
    }

    /** TC-076: per tipe garasi berisi S, C, E termasuk tipe tanpa sewa, dengan filter rentang tanggal opsional. */
    public function test_tc076_report_by_garage_type(): void
    {
        $this->rental('2026-11-01', 50000, GarageType::BICYCLE);
        $this->rental('2026-11-02', 300000, GarageType::CAR);
        $this->rental('2026-11-20', 400000, GarageType::CAR);

        $rows = $this->reports->byGarageType(null, null);

        $this->assertSame(['Sepeda', 'Mobil', 'Mobil Listrik'], $rows->pluck('label')->all());
        $this->assertSame([1, 2, 0], $rows->pluck('rentals')->all());
        $this->assertEquals([50000, 700000, 0], $rows->pluck('revenue')->all());

        $filtered = $this->reports->byGarageType(Carbon::parse('2026-11-02'), Carbon::parse('2026-11-19'));
        $this->assertSame([0, 1, 0], $filtered->pluck('rentals')->all());
    }

    /** TC-077: per penyewa, satu baris per penyewa yang punya sewa, urut pendapatan terbesar. */
    public function test_tc077_report_by_renter(): void
    {
        $budi = User::factory()->create(['name' => 'Budi']);
        $sari = User::factory()->create(['name' => 'Sari']);
        User::factory()->create(['name' => 'Tanpa Sewa']);

        $this->rental('2026-11-01', 100000, renter: $budi);
        $this->rental('2026-11-05', 200000, renter: $budi);
        $this->rental('2026-11-03', 500000, renter: $sari);

        $rows = $this->reports->byRenter(null, null);

        $this->assertSame(['Sari', 'Budi'], $rows->pluck('label')->all());
        $this->assertSame([1, 2], $rows->pluck('rentals')->all());
        $this->assertEquals([500000, 300000], $rows->pluck('revenue')->all());
        $this->assertSame($budi->email, $rows[1]['detail']);

        $filtered = $this->reports->byRenter(Carbon::parse('2026-11-04'), null);
        $this->assertSame(['Budi'], $filtered->pluck('label')->all());
    }

    /** TC-088: sewa yang pembayarannya belum diterima atau ditolak tidak dihitung di laporan (BR-12.5). */
    public function test_tc088_only_approved_payments_are_reported(): void
    {
        $this->rental('2026-11-21', 300000);
        Rental::factory()->pendingPayment()->for(Garage::factory())->between('2026-11-22', '2026-11-24')->create(['total_cost' => 999999]);
        Rental::factory()->rejectedPayment()->for(Garage::factory())->between('2026-11-22', '2026-11-24')->create(['total_cost' => 888888]);

        $november = $this->reports->monthlyInYear(2026)[10];

        $this->assertSame(1, $november['rentals']);
        $this->assertEquals(300000, $november['revenue']);
        $this->assertSame([1, 300000.0], $this->totals($this->reports->byRenter(null, null)));
    }

    /** TC-078: pendapatan = total_cost (sudah termasuk isi listrik) + seluruh perpanjangan. */
    public function test_tc078_revenue_includes_extensions(): void
    {
        $rental = $this->rental('2026-11-21', 543750, GarageType::ELECTRIC_CAR);
        $this->extend($rental, 450000);
        $this->extend($rental, 150000);
        $this->rental('2026-11-22', 100000);

        $november = $this->reports->monthlyInYear(2026)[10];

        $this->assertSame(2, $november['rentals']);
        $this->assertEquals(543750 + 450000 + 150000 + 100000, $november['revenue']);
        $this->assertEquals(1143750, $this->reports->byGarageType(null, null)[2]['revenue']);
    }
}
