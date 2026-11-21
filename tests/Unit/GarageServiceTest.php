<?php

namespace Tests\Unit;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageSequence;
use App\Models\Rental;
use App\Services\GarageService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit test GarageService dengan database (TC-025..TC-034).
 */
class GarageServiceTest extends TestCase
{
    use RefreshDatabase;

    private GarageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GarageService::class);
    }

    /**
     * @return list<string>
     */
    private function codes(GarageType $type): array
    {
        return Garage::where('garage_type', $type)->orderBy('code')->pluck('code')->all();
    }

    private function setLastNumber(GarageType $type, int $number): void
    {
        GarageSequence::whereKey($type->value)->update(['last_number' => $number]);
    }

    /** TC-025: pembuatan pertama garasi sepeda menghasilkan S001..S003. */
    public function test_tc025_first_bicycle_garages(): void
    {
        $garages = $this->service->createGarages(GarageType::BICYCLE, 3, 5000, null);

        $this->assertSame(['S001', 'S002', 'S003'], $garages->pluck('code')->all());
        $this->assertSame(['S001', 'S002', 'S003'], $this->codes(GarageType::BICYCLE));
    }

    /** TC-026: garasi mobil kesepuluh berkode C010. */
    public function test_tc026_tenth_car_garage_is_c010(): void
    {
        $this->service->createGarages(GarageType::CAR, 9, 100000, null);
        $tenth = $this->service->createGarages(GarageType::CAR, 1, 100000, null);

        $this->assertSame(['C010'], $tenth->pluck('code')->all());
    }

    /** TC-027: nomor urut tiap tipe tidak saling memengaruhi. */
    public function test_tc027_sequence_is_independent_per_type(): void
    {
        $this->service->createGarages(GarageType::BICYCLE, 2, 5000, null);
        $this->service->createGarages(GarageType::CAR, 4, 100000, null);
        $this->service->createGarages(GarageType::ELECTRIC_CAR, 1, 150000, 2500);
        $this->service->createGarages(GarageType::BICYCLE, 1, 5000, null);

        $this->assertSame(['S001', 'S002', 'S003'], $this->codes(GarageType::BICYCLE));
        $this->assertSame(['C001', 'C002', 'C003', 'C004'], $this->codes(GarageType::CAR));
        $this->assertSame(['E001'], $this->codes(GarageType::ELECTRIC_CAR));
    }

    /** TC-028: pembuatan kedua melanjutkan nomor terakhir. */
    public function test_tc028_second_creation_continues_numbering(): void
    {
        $this->service->createGarages(GarageType::BICYCLE, 3, 5000, null);
        $second = $this->service->createGarages(GarageType::BICYCLE, 5, 5000, null);

        $this->assertSame(['S004', 'S005', 'S006', 'S007', 'S008'], $second->pluck('code')->all());
        $this->assertSame(8, GarageSequence::find(GarageType::BICYCLE->value)->last_number);
    }

    /** TC-029: melewati 999 ditolak seluruhnya, tidak ada garasi tersimpan. */
    public function test_tc029_exceeding_999_is_rejected_entirely(): void
    {
        $this->setLastNumber(GarageType::CAR, 998);

        try {
            $this->service->createGarages(GarageType::CAR, 3, 100000, null);
            $this->fail('Pembuatan yang melewati 999 seharusnya ditolak');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('1', $e->getMessage());
        }

        $this->assertSame(0, Garage::count());
        $this->assertSame(998, GarageSequence::find(GarageType::CAR->value)->last_number);
    }

    /** TC-030: 999 pada data kosong diterima, 1000 ditolak, 0 ditolak. */
    public function test_tc030_quantity_limits(): void
    {
        $garages = $this->service->createGarages(GarageType::BICYCLE, 999, 5000, null);

        $this->assertCount(999, $garages);
        $this->assertSame('S999', $garages->last()->code);

        foreach ([0, 1000] as $quantity) {
            try {
                $this->service->createGarages(GarageType::CAR, $quantity, 100000, null);
                $this->fail("Jumlah {$quantity} seharusnya ditolak");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Garage::where('garage_type', GarageType::CAR)->count());
    }

    /** TC-034: kode selalu huruf + 3 digit dengan nol di depan. */
    public function test_tc034_code_format(): void
    {
        $garages = $this->service->createGarages(GarageType::ELECTRIC_CAR, 12, 150000, 2500);

        foreach ($garages as $garage) {
            $this->assertMatchesRegularExpression('/^E\d{3}$/', $garage->code);
        }
        $this->assertSame('E001', $garages->first()->code);
        $this->assertSame('E012', $garages->last()->code);
    }

    /** Harga dan status tersimpan di setiap garasi yang dibuat. */
    public function test_prices_and_status_are_stored_on_each_garage(): void
    {
        $this->service->createGarages(GarageType::ELECTRIC_CAR, 2, 150000, 2500);

        foreach (Garage::all() as $garage) {
            $this->assertEquals(150000, $garage->daily_price);
            $this->assertEquals(2500, $garage->kwh_price);
            $this->assertSame(GarageStatus::AVAILABLE, $garage->status);
        }
    }

    /** Harga kWh wajib untuk garasi E dan tidak boleh untuk garasi lain. */
    public function test_kwh_price_rules(): void
    {
        $invalid = [
            [GarageType::ELECTRIC_CAR, null],
            [GarageType::ELECTRIC_CAR, 0.0],
            [GarageType::CAR, 2500.0],
            [GarageType::BICYCLE, 2500.0],
        ];

        foreach ($invalid as [$type, $kwhPrice]) {
            try {
                $this->service->createGarages($type, 1, 100000, $kwhPrice);
                $this->fail("{$type->value} dengan kWh ".var_export($kwhPrice, true).' seharusnya ditolak');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Garage::count());
    }

    /** Harga sewa harian harus lebih dari 0. */
    public function test_daily_price_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createGarages(GarageType::CAR, 1, 0, null);
    }

    /** TC-051: kegagalan di tengah pembuatan membatalkan semuanya (transaksi). */
    public function test_tc051_failure_midway_rolls_back_everything(): void
    {
        // Kode S002 sudah dipakai sehingga penyimpanan S001..S003 gagal di tengah.
        Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S002']);

        try {
            $this->service->createGarages(GarageType::BICYCLE, 3, 5000, null);
            $this->fail('Penyimpanan seharusnya gagal karena kode bentrok');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(['S002'], $this->codes(GarageType::BICYCLE));
        $this->assertSame(0, GarageSequence::find(GarageType::BICYCLE->value)->last_number);
    }

    /** TC-031: garasi tersedia bila tidak ada sewa yang rentangnya bertabrakan dengan rentang yang diminta. */
    public function test_tc031_availability_follows_rental_date_ranges(): void
    {
        $type = GarageType::CAR;
        $free = Garage::factory()->ofType($type)->create(['code' => 'C901']);
        $overlapStart = Garage::factory()->ofType($type)->used()->create(['code' => 'C902']);
        $overlapMiddle = Garage::factory()->ofType($type)->used()->create(['code' => 'C903']);
        $covering = Garage::factory()->ofType($type)->used()->create(['code' => 'C904']);
        $endsAtStart = Garage::factory()->ofType($type)->used()->create(['code' => 'C905']);
        $startsAtEnd = Garage::factory()->ofType($type)->used()->create(['code' => 'C906']);

        // Rentang yang diminta: 2 Jan s.d. 5 Jan 2027 (dipakai 2, 3, 4 Jan).
        Rental::factory()->for($overlapStart)->between('2026-12-30', '2027-01-03')->create();
        Rental::factory()->for($overlapMiddle)->between('2027-01-03', '2027-01-10')->create();
        Rental::factory()->for($covering)->between('2026-12-01', '2027-02-01')->create();
        Rental::factory()->for($endsAtStart)->between('2026-12-28', '2027-01-02')->create();
        Rental::factory()->for($startsAtEnd)->between('2027-01-05', '2027-01-08')->create();

        $available = $this->service->availableGarages($type, Carbon::parse('2027-01-02'), Carbon::parse('2027-01-05'));

        $this->assertSame(['C901', 'C905', 'C906'], $available->pluck('code')->all());
        $this->assertTrue($free->isAvailableBetween(Carbon::parse('2027-01-02'), Carbon::parse('2027-01-05')));
        $this->assertFalse($overlapMiddle->isAvailableBetween(Carbon::parse('2027-01-02'), Carbon::parse('2027-01-05')));
        $this->assertTrue($endsAtStart->isAvailableOn(Carbon::parse('2027-01-02')));
        $this->assertFalse($endsAtStart->isAvailableOn(Carbon::parse('2027-01-01')));
    }

    /** TC-032: hanya garasi dengan tipe yang diminta. */
    public function test_tc032_available_garages_filtered_by_type(): void
    {
        Garage::factory()->ofType(GarageType::BICYCLE)->create(['code' => 'S901']);
        Garage::factory()->ofType(GarageType::CAR)->create(['code' => 'C901']);
        Garage::factory()->ofType(GarageType::ELECTRIC_CAR)->create(['code' => 'E901']);

        $start = today();
        $end = today()->addDays(3);

        $this->assertSame(['E901'], $this->service->availableGarages(GarageType::ELECTRIC_CAR, $start, $end)->pluck('code')->all());
        $this->assertSame(['S901'], $this->service->availableGarages(GarageType::BICYCLE, $start, $end)->pluck('code')->all());
    }

    /** TC-033: garasi used kembali available bila semua sewanya berakhir; sewa berjalan atau akan datang tetap used. */
    public function test_tc033_release_expired_garages(): void
    {
        Carbon::setTestNow('2026-11-21 00:05');

        $ended = Garage::factory()->used()->create(['code' => 'C901']);
        $endsToday = Garage::factory()->used()->create(['code' => 'C902']);
        $active = Garage::factory()->used()->create(['code' => 'C903']);
        $upcoming = Garage::factory()->used()->create(['code' => 'C904']);

        Rental::factory()->for($ended)->between('2026-11-10', '2026-11-15')->create();
        Rental::factory()->for($endsToday)->between('2026-11-18', '2026-11-21')->create();
        Rental::factory()->for($active)->between('2026-11-20', '2026-11-30')->create();
        Rental::factory()->for($upcoming)->between('2026-12-01', '2026-12-05')->create();

        $released = $this->service->releaseExpired(today());

        $this->assertSame(2, $released);
        $this->assertSame(GarageStatus::AVAILABLE, $ended->refresh()->status);
        $this->assertSame(GarageStatus::AVAILABLE, $endsToday->refresh()->status);
        $this->assertSame(GarageStatus::USED, $active->refresh()->status);
        $this->assertSame(GarageStatus::USED, $upcoming->refresh()->status);
    }

    /** Sewa yang pembayarannya ditolak tidak menahan garasi: garasi tersedia dan statusnya dilepas (BR-12.4). */
    public function test_rejected_rentals_do_not_hold_garages(): void
    {
        Carbon::setTestNow('2026-11-21 00:05');
        $garage = Garage::factory()->used()->create(['code' => 'C901']);
        Rental::factory()->rejectedPayment()->for($garage)->between('2026-11-20', '2026-11-30')->create();

        $available = $this->service->availableGarages(GarageType::CAR, Carbon::parse('2026-11-21'), Carbon::parse('2026-11-24'));

        $this->assertSame(['C901'], $available->pluck('code')->all());
        $this->assertSame(1, $this->service->releaseExpired(today()));
        $this->assertSame(GarageStatus::AVAILABLE, $garage->refresh()->status);
    }

    /** nextCodes menampilkan kode berikutnya tanpa menyimpan apa pun. */
    public function test_next_codes_preview_does_not_change_sequence(): void
    {
        $this->setLastNumber(GarageType::CAR, 9);

        $this->assertSame(['C010', 'C011'], $this->service->nextCodes(GarageType::CAR, 2));
        $this->assertSame(9, GarageSequence::find(GarageType::CAR->value)->last_number);
    }

    /** remainingCapacity menghitung sisa kode yang masih bisa dibuat per tipe. */
    public function test_remaining_capacity(): void
    {
        $this->assertSame(999, $this->service->remainingCapacity(GarageType::BICYCLE));

        $this->setLastNumber(GarageType::BICYCLE, 995);

        $this->assertSame(4, $this->service->remainingCapacity(GarageType::BICYCLE));
    }
}
