<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pengguna aplikasi: admin atau penyewa.
 *
 * `role` dan `verification_status` sengaja tidak fillable agar tidak bisa diisi lewat form.
 */
#[Fillable(['nik', 'name', 'phone', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'verification_status' => VerificationStatus::class,
        ];
    }

    /**
     * Kendaraan milik penyewa.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Sewa yang dibuat penyewa.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Perpanjangan sewa yang diproses admin.
     *
     * @return HasMany<RentalExtension, $this>
     */
    public function processedExtensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class, 'processed_by');
    }

    /**
     * Apakah pengguna ber-role admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Apakah pengguna adalah penyewa yang sudah disetujui admin (BR-09.3).
     */
    public function isApprovedRenter(): bool
    {
        return $this->role === UserRole::PENYEWA
            && $this->verification_status === VerificationStatus::APPROVED;
    }
}
