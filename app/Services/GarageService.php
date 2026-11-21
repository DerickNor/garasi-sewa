<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageSequence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembuatan garasi berkelompok dengan kode otomatis (REQ-GAR-01, BR-05) dan ketersediaan garasi (BR-06).
 */
class GarageService
{
    /** Panjang bagian angka pada kode garasi, mis. 001 pada S001. */
    private const CODE_DIGITS = 3;

    /**
     * Buat $quantity garasi bertipe $type sekaligus dalam satu transaksi.
     *
     * Baris penghitung tipe dikunci (lockForUpdate) agar dua admin yang menyimpan bersamaan
     * tidak mendapat kode yang sama. Bila ada yang gagal, tidak ada garasi yang tersimpan.
     *
     * @param  float|null  $kwhPrice  harga isi listrik per kWh, wajib hanya untuk garasi mobil listrik
     * @return Collection<int, Garage> garasi yang dibuat, urut kode
     *
     * @throws InvalidArgumentException bila input tidak valid atau kode melewati batas 999
     */
    public function createGarages(GarageType $type, int $quantity, float $dailyPrice, ?float $kwhPrice): Collection
    {
        $this->validateInput($type, $quantity, $dailyPrice, $kwhPrice);

        return DB::transaction(function () use ($type, $quantity, $dailyPrice, $kwhPrice) {
            // 1. Kunci baris penghitung tipe ini. Admin lain yang menyimpan bersamaan harus menunggu
            //    sampai transaksi ini selesai, sehingga tidak ada dua garasi dengan kode yang sama.
            $sequence = GarageSequence::whereKey($type->value)->lockForUpdate()->firstOrFail();

            // 2. Kode maksimal 999 per tipe; bila tidak cukup, seluruh pembuatan dibatalkan (BR-05.3).
            $remaining = GarageSequence::MAX_NUMBER - $sequence->last_number;
            if ($quantity > $remaining) {
                throw new InvalidArgumentException(
                    "Sisa kode garasi {$type->label()} hanya {$remaining}, tidak cukup untuk {$quantity} garasi."
                );
            }

            // 3. Kode lanjutan dari nomor terakhir, mis. last_number 9 + 3 garasi mobil -> C010, C011, C012.
            $codes = $this->codesAfter($type, $sequence->last_number, $quantity);
            $now = now();

            // 4. Simpan semua garasi sekaligus; harga yang diinput admin disimpan di setiap garasi.
            Garage::insert(array_map(fn (string $code): array => [
                'code' => $code,
                'garage_type' => $type->value,
                'daily_price' => $dailyPrice,
                'kwh_price' => $kwhPrice,
                'status' => GarageStatus::AVAILABLE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $codes));

            // 5. Catat nomor terakhir yang terpakai untuk pembuatan berikutnya.
            $sequence->last_number += $quantity;
            $sequence->save();

            return Garage::whereIn('code', $codes)->orderBy('code')->get();
        });
    }

    /**
     * Kode yang akan dipakai bila $quantity garasi bertipe $type dibuat sekarang (tanpa menyimpan).
     *
     * @return list<string>
     */
    public function nextCodes(GarageType $type, int $quantity): array
    {
        $lastNumber = GarageSequence::whereKey($type->value)->value('last_number') ?? 0;

        return $this->codesAfter($type, $lastNumber, $quantity);
    }

    /**
     * Jumlah garasi bertipe $type yang masih bisa dibuat sebelum mencapai kode 999.
     */
    public function remainingCapacity(GarageType $type): int
    {
        $lastNumber = GarageSequence::whereKey($type->value)->value('last_number') ?? 0;

        return GarageSequence::MAX_NUMBER - $lastNumber;
    }

    /**
     * Garasi bertipe $type yang kosong untuk seluruh rentang [$start, $end), urut kode (REQ-RENT-04, BR-06.1).
     *
     * Ketersediaan diputuskan dari tanggal sewa, bukan dari kolom status.
     *
     * @return Collection<int, Garage>
     */
    public function availableGarages(GarageType $type, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Garage::where('garage_type', $type)
            ->whereDoesntHave('rentals', fn ($rentals) => $rentals->overlapping($start, $end))
            ->orderBy('code')
            ->get();
    }

    /**
     * Kembalikan status garasi `used` yang semua sewanya sudah berakhir pada $today menjadi `available`
     * (BR-06.3, ASM-13). Garasi dengan sewa yang sedang berjalan atau akan datang tetap `used`.
     * Sewa yang pembayarannya ditolak tidak lagi menahan garasi (BR-12.4).
     *
     * @return int jumlah garasi yang dilepas
     */
    public function releaseExpired(CarbonInterface $today): int
    {
        return Garage::where('status', GarageStatus::USED)
            ->whereDoesntHave('rentals', fn ($rentals) => $rentals->notRejected()->whereDate('end_date', '>', $today->toDateString()))
            ->update(['status' => GarageStatus::AVAILABLE]);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateInput(GarageType $type, int $quantity, float $dailyPrice, ?float $kwhPrice): void
    {
        if ($quantity < 1 || $quantity > GarageSequence::MAX_NUMBER) {
            throw new InvalidArgumentException('Jumlah garasi harus 1 sampai '.GarageSequence::MAX_NUMBER.'.');
        }

        if ($dailyPrice <= 0) {
            throw new InvalidArgumentException('Harga sewa harian harus lebih dari 0.');
        }

        if ($type === GarageType::ELECTRIC_CAR && ($kwhPrice === null || $kwhPrice <= 0)) {
            throw new InvalidArgumentException('Harga isi listrik per kWh wajib diisi untuk garasi mobil listrik.');
        }

        if ($type !== GarageType::ELECTRIC_CAR && $kwhPrice !== null) {
            throw new InvalidArgumentException('Harga isi listrik hanya untuk garasi mobil listrik.');
        }
    }

    /**
     * @return list<string>
     */
    private function codesAfter(GarageType $type, int $lastNumber, int $quantity): array
    {
        return array_map(
            fn (int $number): string => $type->prefix().str_pad((string) $number, self::CODE_DIGITS, '0', STR_PAD_LEFT),
            range($lastNumber + 1, $lastNumber + $quantity),
        );
    }
}
