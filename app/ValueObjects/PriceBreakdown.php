<?php

namespace App\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Hasil perhitungan biaya sewa baru (REQ-RENT-05, REQ-RENT-06).
 */
final readonly class PriceBreakdown
{
    /**
     * @param  int  $durationDays  lama sewa dalam hari
     * @param  CarbonImmutable  $endDate  tanggal selesai (eksklusif, ASM-01)
     * @param  float  $rentalFee  biaya sewa garasi
     * @param  float  $chargingFee  biaya isi listrik (0 bila bukan mobil listrik di garasi E)
     * @param  float  $totalCost  biaya sewa + biaya isi listrik
     */
    public function __construct(
        public int $durationDays,
        public CarbonImmutable $endDate,
        public float $rentalFee,
        public float $chargingFee,
        public float $totalCost,
    ) {}
}
