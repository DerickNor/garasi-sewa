<?php

namespace App\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use Carbon\CarbonInterface;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu unit garasi dengan kode S/C/E + 3 digit (BR-05).
 */
#[Fillable(['garage_batch_id', 'code', 'garage_type', 'status'])]
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
            'status' => GarageStatus::class,
        ];
    }

    /**
     * Batch asal garasi (sumber harga).
     *
     * @return BelongsTo<GarageBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(GarageBatch::class, 'garage_batch_id');
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
        $day = $date->toDateString();

        return ! $this->rentals()
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>', $day)
            ->exists();
    }
}
