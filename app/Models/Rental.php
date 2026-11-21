<?php

namespace App\Models;

use App\Enums\RentalType;
use Carbon\CarbonInterface;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transaksi sewa satu garasi untuk satu kendaraan. Harga disalin saat sewa dibuat (BR-08).
 */
#[Fillable([
    'user_id', 'garage_id', 'vehicle_id', 'rental_type', 'duration_days', 'start_date', 'end_date',
    'daily_price', 'kwh_price', 'battery_start_kwh', 'rental_fee', 'charging_fee', 'total_cost',
    'payment_proof_path',
])]
class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rental_type' => RentalType::class,
            'duration_days' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'daily_price' => 'decimal:2',
            'kwh_price' => 'decimal:2',
            'battery_start_kwh' => 'decimal:2',
            'rental_fee' => 'decimal:2',
            'charging_fee' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    /**
     * Penyewa.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Garasi yang disewa.
     *
     * @return BelongsTo<Garage, $this>
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /**
     * Kendaraan yang ditempatkan.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Riwayat perpanjangan sewa ini.
     *
     * @return HasMany<RentalExtension, $this>
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class);
    }

    /**
     * Batasi query ke sewa yang aktif pada tanggal $date: start_date <= $date < end_date (ASM-01).
     *
     * @param  Builder<Rental>  $query
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): void
    {
        $day = $date->toDateString();

        $query->whereDate('start_date', '<=', $day)->whereDate('end_date', '>', $day);
    }

    /**
     * Sewa aktif pada tanggal $date bila start_date <= $date < end_date (ASM-01).
     */
    public function isActiveOn(CarbonInterface $date): bool
    {
        $day = $date->copy()->startOfDay();

        return $this->start_date->lte($day) && $this->end_date->gt($day);
    }

    /**
     * Total pendapatan sewa: biaya awal ditambah seluruh biaya perpanjangan (ASM-09).
     */
    public function totalWithExtensions(): string
    {
        $extensions = (string) $this->extensions()->sum('extra_cost');

        return bcadd((string) $this->total_cost, $extensions, 2);
    }
}
