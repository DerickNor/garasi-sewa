<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status verifikasi bukti pembayaran sewa baru oleh admin (BR-12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->enum('payment_status', array_column(PaymentStatus::cases(), 'value'))
                ->default(PaymentStatus::PENDING->value)
                ->after('payment_proof_path');
            $table->timestamp('payment_verified_at')->nullable()->after('payment_status');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_verified_at')
                ->constrained('users')->restrictOnDelete();

            $table->index('payment_status');
        });

        // Sewa yang sudah ada sebelum fitur ini dianggap sudah lunas.
        DB::table('rentals')->update(['payment_status' => PaymentStatus::APPROVED->value]);
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'payment_verified_at']);
        });
    }
};
