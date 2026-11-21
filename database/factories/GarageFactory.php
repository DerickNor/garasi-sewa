<?php

namespace Database\Factories;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory garasi untuk test. Default: garasi mobil Rp100.000/hari.
 * Kode diambil dari rentang 900–999 agar tidak bentrok dengan kode dari GarageService (mulai 001).
 *
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fn (array $attributes) => $this->codeFor($attributes['garage_type']),
            'garage_type' => GarageType::CAR,
            'daily_price' => 100000,
            'kwh_price' => null,
            'status' => GarageStatus::AVAILABLE,
        ];
    }

    /**
     * Garasi bertipe tertentu; tipe E mendapat harga isi listrik Rp2.500/kWh.
     */
    public function ofType(GarageType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_type' => $type,
            'kwh_price' => $type === GarageType::ELECTRIC_CAR ? 2500 : null,
        ]);
    }

    /**
     * Garasi berstatus used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['status' => GarageStatus::USED]);
    }

    private function codeFor(GarageType|string $type): string
    {
        $type = $type instanceof GarageType ? $type : GarageType::from($type);

        return $type->prefix().fake()->unique()->numberBetween(900, 999);
    }
}
