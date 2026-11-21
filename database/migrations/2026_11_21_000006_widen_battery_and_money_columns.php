<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapasitas baterai tidak dibatasi aturan bisnis, sehingga kolom baterai dan kolom nilai uang yang
 * bergantung padanya (biaya isi listrik, total, kurang bayar) diperbesar agar tidak meluap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('battery_capacity_kwh', 10, 2)->nullable()->change();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('battery_start_kwh', 10, 2)->nullable()->change();
            $table->decimal('rental_fee', 20, 2)->change();
            $table->decimal('charging_fee', 20, 2)->default(0)->change();
            $table->decimal('total_cost', 20, 2)->change();
        });

        Schema::table('rental_extensions', function (Blueprint $table) {
            $table->decimal('extra_cost', 20, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('battery_capacity_kwh', 6, 2)->nullable()->change();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('battery_start_kwh', 6, 2)->nullable()->change();
            $table->decimal('rental_fee', 14, 2)->change();
            $table->decimal('charging_fee', 14, 2)->default(0)->change();
            $table->decimal('total_cost', 14, 2)->change();
        });

        Schema::table('rental_extensions', function (Blueprint $table) {
            $table->decimal('extra_cost', 14, 2)->change();
        });
    }
};
