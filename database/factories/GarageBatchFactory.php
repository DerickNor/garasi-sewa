<?php

namespace Database\Factories;

use App\Enums\GarageType;
use App\Models\GarageBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory batch garasi (dipakai GarageFactory). Default: garasi mobil Rp100.000/hari.
 *
 * @extends Factory<GarageBatch>
 */
class GarageBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'garage_type' => GarageType::CAR,
            'quantity' => 1,
            'daily_price' => 100000,
            'kwh_price' => null,
            'created_by' => User::factory()->admin(),
        ];
    }

    /**
     * Batch untuk tipe garasi tertentu; tipe E mendapat harga isi listrik.
     */
    public function ofType(GarageType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_type' => $type,
            'kwh_price' => $type === GarageType::ELECTRIC_CAR ? 2500 : null,
        ]);
    }
}
