<?php

namespace App\ValueObjects;

/**
 * Hasil perhitungan kurang bayar perpanjangan sewa (REQ-EXT-03, BR-07).
 */
final readonly class ExtensionBreakdown
{
    /**
     * @param  int  $months  jumlah bulan penuh (dihargai 25 × harian)
     * @param  int  $days  sisa hari (dihargai per hari)
     * @param  float  $cost  total kurang bayar, selalu > 0
     */
    public function __construct(
        public int $months,
        public int $days,
        public float $cost,
    ) {}
}
