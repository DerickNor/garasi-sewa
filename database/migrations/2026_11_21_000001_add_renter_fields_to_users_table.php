<?php

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah data penyewa, role, dan status verifikasi ke tabel users (REQ-AUTH-01, BR-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('id');
            $table->string('phone', 20)->nullable()->after('name');
            $table->enum('role', array_column(UserRole::cases(), 'value'))
                ->default(UserRole::PENYEWA->value)
                ->after('password');
            $table->enum('verification_status', array_column(VerificationStatus::cases(), 'value'))
                ->default(VerificationStatus::PENDING->value)
                ->after('role');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn(['nik', 'phone', 'role', 'verification_status', 'verified_at']);
        });
    }
};
