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
     * Batasi query ke sewa yang rentangnya bertabrakan dengan [$start, $end) (BR-06.1, ASM-01).
     *
     * Dua rentang bertabrakan bila sewa.start_date < $end dan sewa.end_date > $start.
     *
     * Contoh untuk permintaan 2–5 Jan (dipakai 2, 3, 4 Jan):
     *   sewa 30 Des–3 Jan   -> bertabrakan (sama-sama memakai 2 Jan)
     *   sewa 3 Jan–10 Jan   -> bertabrakan (sama-sama memakai 3 dan 4 Jan)
     *   sewa 28 Des–2 Jan   -> tidak bertabrakan (dikembalikan tepat 2 Jan)
     *   sewa 5 Jan–8 Jan    -> tidak bertabrakan (mulai tepat saat garasi dikembalikan)
     *
     * @param  Builder<Rental>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->whereDate('start_date', '<', $end->toDateString())
            ->whereDate('end_date', '>', $start->toDateString());
    }

    /**
     * Batasi query ke sewa yang aktif pada tanggal $date: start_date <= $date < end_date (ASM-01).
     *
     * @param  Builder<Rental>  $query
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): void
    {
        $day = $date->copy()->startOfDay();

        $this->scopeOverlapping($query, $day, $day->copy()->addDay());
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
        $extensions = (string) ($this->relationLoaded('extensions')
            ? $this->extensions->sum('extra_cost')
            : $this->extensions()->sum('extra_cost'));

        return bcadd((string) $this->total_cost, $extensions, 2);
    }
}
