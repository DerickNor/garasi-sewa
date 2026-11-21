<?php

namespace App\Models;

use App\Enums\GarageType;
use Database\Factories\GarageBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kali aksi admin "buat garasi berkelompok", menyimpan harga untuk garasi di dalamnya (REQ-GAR-01).
 */
#[Fillable(['garage_type', 'quantity', 'daily_price', 'kwh_price', 'created_by'])]
class GarageBatch extends Model
{
    /** @use HasFactory<GarageBatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'garage_type' => GarageType::class,
            'quantity' => 'integer',
            'daily_price' => 'decimal:2',
            'kwh_price' => 'decimal:2',
        ];
    }

    /**
     * Admin yang membuat batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Garasi yang dihasilkan batch ini.
     *
     * @return HasMany<Garage, $this>
     */
    public function garages(): HasMany
    {
        return $this->hasMany(Garage::class);
    }
}
