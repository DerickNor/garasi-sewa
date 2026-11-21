<?php

namespace App\Models;

use App\Enums\VehicleType;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kendaraan milik penyewa (REQ-RENT-02).
 */
#[Fillable(['user_id', 'type', 'plate_number', 'brand', 'model', 'battery_capacity_kwh'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'battery_capacity_kwh' => 'decimal:2',
        ];
    }

    /**
     * Seragamkan penulisan nomor plat: huruf besar dan satu spasi antara huruf dan angka, mis. "l1234ab" → "L 1234 AB".
     */
    public static function normalizePlate(?string $plate): ?string
    {
        $compact = strtoupper(preg_replace('/\s+/', '', (string) $plate));

        if ($compact === '') {
            return null;
        }

        return trim(preg_replace('/(?<=[A-Z])(?=\d)|(?<=\d)(?=[A-Z])/', ' ', $compact));
    }

    /**
     * Pemilik kendaraan.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sewa yang memakai kendaraan ini.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
