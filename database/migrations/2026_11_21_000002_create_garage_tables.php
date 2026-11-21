<?php

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel garasi: batch (harga per pembuatan), penghitung kode per tipe, dan unit garasi
 * (REQ-GAR-01, BR-02, BR-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        $garageTypes = array_column(GarageType::cases(), 'value');

        Schema::create('garage_batches', function (Blueprint $table) use ($garageTypes) {
            $table->id();
            $table->enum('garage_type', $garageTypes);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('daily_price', 12, 2);
            $table->decimal('kwh_price', 12, 2)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('garage_sequences', function (Blueprint $table) use ($garageTypes) {
            $table->enum('garage_type', $garageTypes)->primary();
            $table->unsignedSmallInteger('last_number')->default(0);
        });

        // Satu baris penghitung per tipe agar bisa dikunci dengan lockForUpdate (BR-05).
        DB::table('garage_sequences')->insert(
            array_map(fn (string $type): array => ['garage_type' => $type, 'last_number' => 0], $garageTypes),
        );

        Schema::create('garages', function (Blueprint $table) use ($garageTypes) {
            $table->id();
            $table->foreignId('garage_batch_id')->constrained()->restrictOnDelete();
            $table->string('code', 4)->unique();
            $table->enum('garage_type', $garageTypes);
            $table->enum('status', array_column(GarageStatus::cases(), 'value'))
                ->default(GarageStatus::AVAILABLE->value);
            $table->timestamps();

            $table->index(['garage_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garages');
        Schema::dropIfExists('garage_sequences');
        Schema::dropIfExists('garage_batches');
    }
};
