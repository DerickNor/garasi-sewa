<?php

namespace App\Enums;

/**
 * Status penanda garasi (BR-06). Ketersediaan tetap diputuskan dari tanggal sewa.
 */
enum GarageStatus: string
{
    case AVAILABLE = 'available';
    case USED = 'used';

    /**
     * Nama status untuk ditampilkan.
     */
    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::USED => 'Disewa',
        };
    }
}
