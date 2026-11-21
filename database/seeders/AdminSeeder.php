<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Membuat akun admin awal (ASM-11). Aman dijalankan berulang.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => config('garasi.admin.email')]);

        $admin->forceFill([
            'name' => config('garasi.admin.name'),
            'password' => config('garasi.admin.password'),
            'role' => UserRole::ADMIN,
            'verification_status' => VerificationStatus::APPROVED,
            'verified_at' => now(),
            'email_verified_at' => now(),
        ])->save();
    }
}
