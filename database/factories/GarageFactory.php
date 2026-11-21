<?php

namespace Database\Factories;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory garasi untuk test. Kode diambil dari rentang 900–999 agar tidak bentrok
 * dengan kode yang dibuat GarageService (yang mulai dari 001).
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
            'garage_batch_id' => GarageBatch::factory(),
            'code' => fn (array $attributes) => $this->codeFor(GarageType::from(
                $attributes['garage_type'] instanceof GarageType
                    ? $attributes['garage_type']->value
                    : $attributes['garage_type'],
            )),
            'garage_type' => GarageType::CAR,
            'status' => GarageStatus::AVAILABLE,
        ];
    }

    /**
     * Garasi bertipe tertentu beserta batch yang sesuai.
     */
    public function ofType(GarageType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'garage_type' => $type,
            'garage_batch_id' => GarageBatch::factory()->ofType($type),
        ]);
    }

    /**
     * Garasi berstatus used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['status' => GarageStatus::USED]);
    }

    private function codeFor(GarageType $type): string
    {
        return $type->prefix().fake()->unique()->numberBetween(900, 999);
    }
}
