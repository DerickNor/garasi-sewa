<?php

namespace App\Enums;

/**
 * Status verifikasi pendaftaran penyewa (BR-09).
 */
enum VerificationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
