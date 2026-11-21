<?php

namespace App\Enums;

/**
 * Status verifikasi bukti pembayaran sewa baru (BR-12).
 *
 * Sewa baru dimulai `pending`; admin menerima (`approved`) atau menolak (`rejected`).
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Nama status untuk ditampilkan.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu verifikasi',
            self::APPROVED => 'Diterima',
            self::REJECTED => 'Pembayaran ditolak',
        };
    }
}
