<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory kendaraan. Default: mobil bensin dengan nomor plat.
 *
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => VehicleType::CAR,
            'plate_number' => strtoupper(fake()->unique()->bothify('L #### ??')),
            'brand' => fake()->randomElement(['Toyota', 'Honda', 'Suzuki', 'Daihatsu']),
            'model' => fake()->randomElement(['Avanza', 'Jazz', 'Ertiga', 'Xenia']),
            'battery_capacity_kwh' => null,
        ];
    }

    /**
     * Sepeda (tanpa plat).
     */
    public function bicycle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VehicleType::BICYCLE,
            'plate_number' => null,
            'brand' => 'Polygon',
            'model' => 'Xtrada',
        ]);
    }

    /**
     * Sepeda motor.
     */
    public function motorcycle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VehicleType::MOTORCYCLE,
            'brand' => 'Honda',
            'model' => 'Vario',
        ]);
    }

    /**
     * Mobil listrik dengan kapasitas baterai 60 kWh.
     */
    public function electric(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VehicleType::ELECTRIC_CAR,
            'brand' => 'Hyundai',
            'model' => 'Ioniq 5',
            'battery_capacity_kwh' => 60,
        ]);
    }
}
