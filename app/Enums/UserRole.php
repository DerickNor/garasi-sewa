<?php

namespace App\Enums;

/**
 * Role pengguna aplikasi.
 */
enum UserRole: string
{
    case ADMIN = 'admin';
    case PENYEWA = 'penyewa';
}
