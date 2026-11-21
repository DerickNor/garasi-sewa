<?php

namespace App\Enums;

/**
 * Jenis sewa garasi (BR-02, BR-03).
 */
enum RentalType: string
{
    case DAILY = 'daily';
    case MONTHLY = 'monthly';
}
