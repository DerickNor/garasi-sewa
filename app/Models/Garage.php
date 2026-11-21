<?php

namespace App\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use Carbon\CarbonInterface;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu unit garasi dengan kode S/C/E + 3 digit (BR-05) dan harga yang diinput admin (BR-02).
 */
#[Fillable(['code', 'garage_type', 'daily_price', 'kwh_price', 'status'])]
class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'garage_type' => GarageType::class,
            'daily_price' => 'decimal:2',
            'kwh_price' => 'decimal:2',
            'status' => GarageStatus::class,
        ];
    }

    /**
     * Riwayat sewa garasi ini.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Garasi tersedia pada tanggal $date bila tidak ada sewa dengan start_date <= $date < end_date (BR-06.1).
     */
    public function isAvailableOn(CarbonInterface $date): bool
    {
        return ! $this->rentals()->activeOn($date)->exists();
    }
}
