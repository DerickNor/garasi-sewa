<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Services\RentalPricingService;
use App\ValueObjects\PriceBreakdown;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit test perhitungan harga dan tanggal (TC-001..TC-020, test-spec.md bagian 1).
 * Harga harian default Rp100.000.
 */
class RentalPricingServiceTest extends TestCase
{
    private const DAILY = 100000.0;

    private RentalPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new RentalPricingService;
    }

    private static function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    /**
     * Sewa mobil bensin di garasi C (tanpa listrik) dengan parameter yang bisa diganti.
     */
    private function carRental(RentalType $type, ?int $days, string $start, float $daily = self::DAILY): PriceBreakdown
    {
        return $this->pricing->calculateNewRental(
            type: $type,
            days: $days,
            start: self::date($start),
            dailyPrice: $daily,
            garageType: GarageType::CAR,
            vehicleType: VehicleType::CAR,
        );
    }

    /**
     * Sewa mobil listrik kapasitas 60 kWh dengan parameter garasi dan baterai yang bisa diganti.
     */
    private function electricRental(
        GarageType $garage,
        float $battery,
        int $days = 3,
        float $daily = self::DAILY,
        float $capacity = 60,
    ): PriceBreakdown {
        return $this->pricing->calculateNewRental(
            type: RentalType::DAILY,
            days: $days,
            start: self::date('2026-11-21'),
            dailyPrice: $daily,
            garageType: $garage,
            vehicleType: VehicleType::ELECTRIC_CAR,
            batteryCapacityKwh: $capacity,
            batteryStartKwh: $battery,
            kwhPrice: $garage === GarageType::ELECTRIC_CAR ? 2500 : null,
        );
    }

    // ---------------------------------------------------------------- BR-03 hari dalam bulan

    /** TC-001: Februari biasa 28 hari. */
    public function test_tc001_days_in_february_regular_year(): void
    {
        $this->assertSame(28, $this->pricing->daysInMonth(self::date('2026-02-10')));
    }

    /** TC-002: Februari kabisat 29 hari. */
    public function test_tc002_days_in_february_leap_year(): void
    {
        $this->assertSame(29, $this->pricing->daysInMonth(self::date('2028-02-10')));
    }

    /** TC-003: bulan 30 dan 31 hari. */
    public function test_tc003_days_in_30_and_31_day_months(): void
    {
        $this->assertSame(30, $this->pricing->daysInMonth(self::date('2026-11-21')));
        $this->assertSame(31, $this->pricing->daysInMonth(self::date('2026-12-01')));
    }

    /** TC-004: maksimum sewa harian = hari dalam bulan − 1. */
    public function test_tc004_max_daily_days(): void
    {
        $this->assertSame(27, $this->pricing->maxDailyDays(self::date('2026-02-10')));
        $this->assertSame(28, $this->pricing->maxDailyDays(self::date('2028-02-10')));
        $this->assertSame(29, $this->pricing->maxDailyDays(self::date('2026-11-21')));
        $this->assertSame(30, $this->pricing->maxDailyDays(self::date('2026-12-01')));
    }

    // ---------------------------------------------------------------- BR-02, BR-03 sewa baru

    /** TC-005: harga bulanan selalu 25 × harian, tidak bergantung panjang bulan. */
    public function test_tc005_monthly_fee_is_always_25_times_daily(): void
    {
        $this->assertEquals(2500000, $this->carRental(RentalType::MONTHLY, null, '2026-02-15')->rentalFee);
        $this->assertEquals(2500000, $this->carRental(RentalType::MONTHLY, null, '2026-12-15')->rentalFee);
    }

    /** TC-006: sewa harian 5 hari mulai 21 Nov 2026. */
    public function test_tc006_daily_rental(): void
    {
        $result = $this->carRental(RentalType::DAILY, 5, '2026-11-21');

        $this->assertEquals(500000, $result->rentalFee);
        $this->assertSame('2026-11-26', $result->endDate->toDateString());
        $this->assertSame(5, $result->durationDays);
        $this->assertEquals(0, $result->chargingFee);
        $this->assertEquals(500000, $result->totalCost);
    }

    /** TC-007: batas hari sewa harian Februari 2026 (1..27). */
    public function test_tc007_daily_days_limit(): void
    {
        $this->assertSame(27, $this->carRental(RentalType::DAILY, 27, '2026-02-01')->durationDays);

        foreach ([0, 28] as $days) {
            try {
                $this->carRental(RentalType::DAILY, $days, '2026-02-01');
                $this->fail("{$days} hari seharusnya ditolak");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function monthlyEndDates(): array
    {
        return [
            'Nov 2026' => ['2026-11-21', '2026-12-21', 30],
            'Feb 2026' => ['2026-02-15', '2026-03-15', 28],
            'Feb 2028 kabisat' => ['2028-02-15', '2028-03-15', 29],
            '31 Jan 2026' => ['2026-01-31', '2026-03-03', 31],
        ];
    }

    /** TC-008: tanggal selesai sewa bulanan = mulai + N hari bulan mulai. */
    #[DataProvider('monthlyEndDates')]
    public function test_tc008_monthly_end_date(string $start, string $expectedEnd, int $expectedDuration): void
    {
        $result = $this->carRental(RentalType::MONTHLY, null, $start);

        $this->assertSame($expectedEnd, $result->endDate->toDateString());
        $this->assertSame($expectedDuration, $result->durationDays);
    }

    // ---------------------------------------------------------------- BR-04 mobil listrik

    /** TC-009: biaya isi listrik di garasi E. */
    public function test_tc009_charging_fee_in_electric_garage(): void
    {
        $this->assertEquals(93750, $this->electricRental(GarageType::ELECTRIC_CAR, 22.5)->chargingFee);
    }

    /** TC-010: baterai sudah penuh, biaya isi 0. */
    public function test_tc010_full_battery_has_no_charging_fee(): void
    {
        $this->assertEquals(0, $this->electricRental(GarageType::ELECTRIC_CAR, 60)->chargingFee);
    }

    /** TC-011: baterai awal di luar 0..kapasitas ditolak. */
    public function test_tc011_battery_out_of_range_is_rejected(): void
    {
        foreach ([61, -1] as $battery) {
            try {
                $this->electricRental(GarageType::ELECTRIC_CAR, $battery);
                $this->fail("baterai {$battery} seharusnya ditolak");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** TC-012: mobil listrik di garasi C tidak dikenai biaya isi listrik. */
    public function test_tc012_electric_car_in_car_garage_has_no_charging_fee(): void
    {
        $result = $this->electricRental(GarageType::CAR, 22.5);

        $this->assertEquals(0, $result->chargingFee);
        $this->assertEquals(300000, $result->totalCost);
    }

    /** TC-013: total biaya mobil listrik = sewa + isi listrik (contoh REQ-RENT-06.5). */
    public function test_tc013_total_cost_electric_car(): void
    {
        $result = $this->electricRental(GarageType::ELECTRIC_CAR, 22.5, days: 3, daily: 200000);

        $this->assertEquals(600000, $result->rentalFee);
        $this->assertEquals(93750, $result->chargingFee);
        $this->assertEquals(693750, $result->totalCost);
    }

    /** TC-014: kendaraan non-listrik tidak dikenai biaya isi walau parameter baterai terisi. */
    public function test_tc014_non_electric_vehicle_ignores_battery(): void
    {
        $result = $this->pricing->calculateNewRental(
            type: RentalType::DAILY,
            days: 3,
            start: self::date('2026-11-21'),
            dailyPrice: self::DAILY,
            garageType: GarageType::CAR,
            vehicleType: VehicleType::CAR,
            batteryCapacityKwh: 60,
            batteryStartKwh: 10,
            kwhPrice: 2500,
        );

        $this->assertEquals(0, $result->chargingFee);
        $this->assertEquals(300000, $result->totalCost);
    }

    // ---------------------------------------------------------------- BR-07 perpanjangan

    /** TC-015: perpanjangan 1 bulan (31 hari) + 3 hari. */
    public function test_tc015_extension_months_and_days(): void
    {
        $result = $this->pricing->calculateExtension(self::date('2026-12-21'), self::date('2027-01-24'), self::DAILY);

        $this->assertSame(1, $result->months);
        $this->assertSame(3, $result->days);
        $this->assertEquals(2800000, $result->cost);
    }

    /** TC-016: perpanjangan hanya hari. */
    public function test_tc016_extension_days_only(): void
    {
        $result = $this->pricing->calculateExtension(self::date('2026-11-10'), self::date('2026-11-15'), self::DAILY);

        $this->assertSame(0, $result->months);
        $this->assertSame(5, $result->days);
        $this->assertEquals(500000, $result->cost);
    }

    /** TC-017: perpanjangan dua bulan dengan panjang bulan berbeda (28 + 31 hari). */
    public function test_tc017_extension_two_months_different_lengths(): void
    {
        $result = $this->pricing->calculateExtension(self::date('2026-02-01'), self::date('2026-04-01'), self::DAILY);

        $this->assertSame(2, $result->months);
        $this->assertSame(0, $result->days);
        $this->assertEquals(5000000, $result->cost);
    }

    /** TC-018: tanggal baru harus lebih besar dari tanggal lama. */
    public function test_tc018_new_end_date_must_be_after_old_end_date(): void
    {
        foreach (['2026-12-21', '2026-12-20'] as $newEnd) {
            try {
                $this->pricing->calculateExtension(self::date('2026-12-21'), self::date($newEnd), self::DAILY);
                $this->fail("tanggal baru {$newEnd} seharusnya ditolak");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** TC-019: perpanjangan Februari kabisat tepat satu bulan (29 hari). */
    public function test_tc019_extension_leap_february(): void
    {
        $result = $this->pricing->calculateExtension(self::date('2028-02-01'), self::date('2028-03-01'), self::DAILY);

        $this->assertSame(1, $result->months);
        $this->assertSame(0, $result->days);
        $this->assertEquals(2500000, $result->cost);
    }

    /** TC-020: kurang bayar selalu positif untuk input valid. */
    public function test_tc020_extension_cost_is_always_positive(): void
    {
        mt_srand(2026);

        for ($i = 0; $i < 200; $i++) {
            $oldEnd = self::date('2026-01-01')->addDays(mt_rand(0, 1500));
            $newEnd = $oldEnd->addDays(mt_rand(1, 400));
            $daily = (float) mt_rand(1, 500) * 1000;

            $result = $this->pricing->calculateExtension($oldEnd, $newEnd, $daily);

            $this->assertGreaterThan(0, $result->cost, "{$oldEnd->toDateString()} → {$newEnd->toDateString()}");
            $this->assertSame(
                (int) $oldEnd->diffInDays($newEnd) > 0,
                $result->months > 0 || $result->days > 0,
            );
        }
    }
}
