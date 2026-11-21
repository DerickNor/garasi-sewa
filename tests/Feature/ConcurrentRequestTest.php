<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageService;
use App\Services\RentalService;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Dua request bersamaan (TC-091, TC-092): membuktikan lockForUpdate mencegah kode garasi ganda dan sewa ganda.
 *
 * Request pertama berjalan sebagai proses PHP terpisah (tests/Fixtures/first_request.php): mengunci baris,
 * menulis data, menahan transaksi 2 detik, lalu commit. Selama itu test menjalankan service sungguhan
 * sebagai request kedua. Tanpa lockForUpdate, request kedua membaca data lama dan menghasilkan data ganda,
 * sehingga test ini gagal.
 *
 * Memakai DatabaseMigrations (database dibuat ulang per test, tanpa transaksi pembungkus) karena
 * RefreshDatabase membungkus test dalam satu transaksi yang membuat data request pertama tidak terlihat.
 */
class ConcurrentRequestTest extends TestCase
{
    use DatabaseMigrations;

    /** Lama request pertama menahan transaksinya. */
    private const HOLD_MS = 2000;

    /** Batas waktu menunggu proses request pertama siap memegang kunci. */
    private const READY_TIMEOUT_SECONDS = 10;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 08:00');
        Storage::fake('public');

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Uji penguncian baris membutuhkan MariaDB atau MySQL.');
        }
    }

    /**
     * Jalankan request pertama di proses terpisah dan tunggu sampai ia memegang kunci.
     *
     * @param  list<array{0: string, 1: list<mixed>}>  $statements  SQL beserta nilai parameternya
     */
    private function startFirstRequest(array $statements): InvokedProcess
    {
        $db = config('database.connections.'.config('database.default'));
        $payload = json_encode([
            'host' => $db['host'], 'port' => $db['port'], 'database' => $db['database'],
            'username' => $db['username'], 'password' => $db['password'],
            'hold_ms' => self::HOLD_MS, 'statements' => $statements,
        ], JSON_THROW_ON_ERROR);

        $process = Process::start([PHP_BINARY, base_path('tests/Fixtures/first_request.php'), $payload]);

        $deadline = microtime(true) + self::READY_TIMEOUT_SECONDS;
        while (! str_contains($process->output(), 'LOCKED')) {
            if (! $process->running() || microtime(true) > $deadline) {
                throw new RuntimeException('Request pertama gagal memegang kunci: '.$process->errorOutput());
            }
            usleep(20_000);
        }

        return $process;
    }

    /** TC-091: dua admin membuat garasi mobil bersamaan tidak mendapat kode yang sama. */
    public function test_tc091_concurrent_garage_creation_does_not_duplicate_codes(): void
    {
        $now = now()->toDateTimeString();
        $insertGarage = 'INSERT INTO garages (code, garage_type, daily_price, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)';

        // Admin A sedang membuat C001–C003: penghitung dikunci, garasi ditulis, belum di-commit.
        $adminA = $this->startFirstRequest([
            ['SELECT * FROM garage_sequences WHERE garage_type = ? FOR UPDATE', ['car']],
            [$insertGarage, ['C001', 'car', 100000, 'available', $now, $now]],
            [$insertGarage, ['C002', 'car', 100000, 'available', $now, $now]],
            [$insertGarage, ['C003', 'car', 100000, 'available', $now, $now]],
            ['UPDATE garage_sequences SET last_number = 3 WHERE garage_type = ?', ['car']],
        ]);

        // Admin B menyimpan pada saat yang sama: menunggu kunci, lalu membaca nomor terbaru (3).
        $startedAt = microtime(true);
        $created = app(GarageService::class)->createGarages(GarageType::CAR, 3, 100000, null);
        $waitedMs = (microtime(true) - $startedAt) * 1000;

        $adminA->wait();
        $this->assertStringContainsString('COMMITTED', $adminA->output());
        $this->assertGreaterThan(self::HOLD_MS / 2, $waitedMs, 'Admin B seharusnya menunggu kunci admin A.');
        $this->assertSame(['C004', 'C005', 'C006'], $created->pluck('code')->all());
        $this->assertSame(
            ['C001', 'C002', 'C003', 'C004', 'C005', 'C006'],
            Garage::orderBy('code')->pluck('code')->all(),
        );
    }

    /** TC-092: dua penyewa menyimpan sewa garasi yang sama bersamaan; yang kedua ditolak, bukan dobel. */
    public function test_tc092_concurrent_rentals_cannot_double_book_a_garage(): void
    {
        $garage = Garage::factory()->ofType(GarageType::CAR)->create(['code' => 'C950', 'daily_price' => 100000]);
        $firstRenter = User::factory()->create();
        $firstVehicle = Vehicle::factory()->create(['user_id' => $firstRenter->id, 'plate_number' => 'L 1111 AA']);
        $now = now()->toDateTimeString();

        // Penyewa pertama sedang menyimpan 21–23 Nov: garasi dikunci, sewa ditulis, belum di-commit.
        $first = $this->startFirstRequest([
            ['SELECT * FROM garages WHERE id = ? FOR UPDATE', [$garage->id]],
            ['INSERT INTO rentals (user_id, garage_id, vehicle_id, rental_type, duration_days, start_date, end_date,
                daily_price, rental_fee, charging_fee, total_cost, payment_proof_path, payment_status, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $firstRenter->id, $garage->id, $firstVehicle->id, 'daily', 3, '2026-11-21', '2026-11-24',
                100000, 300000, 0, 300000, 'proofs/pertama.png', 'pending', $now, $now,
            ]],
            ['UPDATE garages SET status = ? WHERE id = ?', ['used', $garage->id]],
        ]);

        // Penyewa kedua memilih garasi dan tanggal yang sama pada saat itu.
        $secondRenter = User::factory()->create();
        $input = [
            'vehicle_type' => 'car', 'plate_number' => 'L 2222 BB', 'brand' => 'Honda', 'model' => 'Jazz',
            'battery_capacity_kwh' => null, 'battery_start_kwh' => null,
            'rental_type' => 'daily', 'days' => 3, 'start_date' => '2026-11-21', 'garage_id' => $garage->id,
        ];

        try {
            app(RentalService::class)->createRental($secondRenter, $input, UploadedFile::fake()->image('bukti.jpg'));
            $this->fail('Sewa kedua seharusnya ditolak karena garasi sudah disewa penyewa pertama.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah disewa', $e->getMessage());
        }

        $first->wait();
        $this->assertStringContainsString('COMMITTED', $first->output());
        $this->assertSame([$firstRenter->id], Rental::where('garage_id', $garage->id)->pluck('user_id')->all());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Bukti sewa yang ditolak ikut dihapus.');
    }
}
