<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory pengguna. Default: penyewa yang sudah disetujui admin.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('################'),
            'name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::PENYEWA,
            'verification_status' => VerificationStatus::APPROVED,
            'verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Akun admin (tanpa NIK dan telepon).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'nik' => null,
            'phone' => null,
            'role' => UserRole::ADMIN,
            'verification_status' => VerificationStatus::APPROVED,
        ]);
    }

    /**
     * Penyewa yang baru mendaftar dan belum diverifikasi.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::PENDING,
            'verified_at' => null,
        ]);
    }

    /**
     * Penyewa yang pendaftarannya ditolak admin.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::REJECTED,
            'verified_at' => null,
        ]);
    }
}
