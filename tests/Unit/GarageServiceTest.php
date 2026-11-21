<?php

namespace Tests\Unit;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageSequence;
use App\Services\GarageService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
