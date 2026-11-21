<?php

use App\Enums\VehicleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kendaraan milik penyewa (REQ-RENT-02, BR-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', array_column(VehicleType::cases(), 'value'));
            $table->string('plate_number', 15)->nullable();
            $table->string('brand');
            $table->string('model');
            $table->decimal('battery_capacity_kwh', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'plate_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
