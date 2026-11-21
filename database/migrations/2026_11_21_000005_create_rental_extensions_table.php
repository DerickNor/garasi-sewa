<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perpanjangan sewa oleh admin (BR-07, REQ-EXT-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->date('old_end_date');
            $table->date('new_end_date');
            $table->unsignedSmallInteger('extra_months');
            $table->unsignedSmallInteger('extra_days');
            $table->decimal('extra_cost', 14, 2);
            $table->string('payment_proof_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_extensions');
    }
};
