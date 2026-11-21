<?php

use App\Enums\RentalType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaksi sewa garasi dengan salinan harga saat sewa dibuat (BR-06, BR-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('garage_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->enum('rental_type', array_column(RentalType::cases(), 'value'));
            $table->unsignedSmallInteger('duration_days');
            $table->date('start_date');
            $table->date('end_date'); // eksklusif (ASM-01), berubah saat perpanjangan
            $table->decimal('daily_price', 12, 2); // snapshot (BR-08)
            $table->decimal('kwh_price', 12, 2)->nullable(); // snapshot (BR-08)
            $table->decimal('battery_start_kwh', 6, 2)->nullable();
            $table->decimal('rental_fee', 14, 2);
            $table->decimal('charging_fee', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2);
            $table->string('payment_proof_path');
            $table->timestamps();

            $table->index(['garage_id', 'start_date', 'end_date']);
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
