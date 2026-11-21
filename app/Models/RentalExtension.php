<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan perpanjangan tanggal selesai sewa oleh admin (BR-07).
 */
#[Fillable([
    'rental_id', 'processed_by', 'old_end_date', 'new_end_date', 'extra_months', 'extra_days',
    'extra_cost', 'payment_proof_path',
])]
class RentalExtension extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_end_date' => 'date',
            'new_end_date' => 'date',
            'extra_months' => 'integer',
            'extra_days' => 'integer',
            'extra_cost' => 'decimal:2',
        ];
    }

    /**
     * Sewa yang diperpanjang.
     *
     * @return BelongsTo<Rental, $this>
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Admin yang memproses perpanjangan.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
