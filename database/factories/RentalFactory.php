<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Factory sewa untuk test: sewa harian 3 hari mulai hari ini, Rp100.000/hari, pembayaran sudah diterima.
 * Nilai biaya ditulis langsung; perhitungan sebenarnya ada di RentalPricingService.
 *
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'garage_id' => Garage::factory(),
            'vehicle_id' => fn (array $attributes) => Vehicle::factory()->create(['user_id' => $attributes['user_id']]),
            'rental_type' => RentalType::DAILY,
            'duration_days' => 3,
            'start_date' => today(),
            'end_date' => today()->addDays(3),
            'daily_price' => 100000,
            'kwh_price' => null,
            'battery_start_kwh' => null,
            'rental_fee' => 300000,
            'charging_fee' => 0,
            'total_cost' => 300000,
            'payment_proof_path' => 'proofs/example.png',
            'payment_status' => PaymentStatus::APPROVED,
        ];
    }

    /**
     * Sewa yang bukti pembayarannya belum diperiksa admin (BR-12.1).
     */
    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => ['payment_status' => PaymentStatus::PENDING]);
    }

    /**
     * Sewa yang pembayarannya ditolak admin (BR-12.4).
     */
    public function rejectedPayment(): static
    {
        return $this->state(fn (array $attributes) => ['payment_status' => PaymentStatus::REJECTED]);
    }

    /**
     * Sewa dengan rentang tanggal tertentu (end eksklusif, ASM-01).
     */
    public function between(string $start, string $end): static
    {
        return $this->state(function (array $attributes) use ($start, $end) {
            $days = (int) Carbon::parse($start)->diffInDays($end);

            return [
                'start_date' => $start,
                'end_date' => $end,
                'duration_days' => $days,
                'rental_fee' => $days * 100000,
                'total_cost' => $days * 100000,
            ];
        });
    }
}
