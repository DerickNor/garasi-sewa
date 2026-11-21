<?php

namespace App\Models;

use App\Enums\GarageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Penghitung nomor urut kode garasi per tipe (BR-05). Satu baris per tipe.
 */
#[Fillable(['garage_type', 'last_number'])]
class GarageSequence extends Model
{
    /** Kode tertinggi per tipe garasi (ASM-07). */
    public const MAX_NUMBER = 999;

    protected $primaryKey = 'garage_type';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'garage_type' => GarageType::class,
            'last_number' => 'integer',
        ];
    }
}
