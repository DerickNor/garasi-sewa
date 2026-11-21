<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageSequence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembuatan garasi berkelompok dengan kode otomatis (REQ-GAR-01, BR-05).
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
            $sequence = GarageSequence::whereKey($type->value)->lockForUpdate()->firstOrFail();

            $remaining = GarageSequence::MAX_NUMBER - $sequence->last_number;
            if ($quantity > $remaining) {
                throw new InvalidArgumentException(
                    "Sisa kode garasi {$type->label()} hanya {$remaining}, tidak cukup untuk {$quantity} garasi."
                );
            }

            $codes = $this->codesAfter($type, $sequence->last_number, $quantity);
            $now = now();

            Garage::insert(array_map(fn (string $code): array => [
                'code' => $code,
                'garage_type' => $type->value,
                'daily_price' => $dailyPrice,
                'kwh_price' => $kwhPrice,
                'status' => GarageStatus::AVAILABLE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $codes));

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
