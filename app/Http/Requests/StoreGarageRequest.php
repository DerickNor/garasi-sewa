<?php

namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Models\GarageSequence;
use App\Services\GarageService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi form buat garasi berkelompok (REQ-GAR-01, design.md bagian 10).
 */
class StoreGarageRequest extends FormRequest
{
    /** Batas atas harga agar muat di kolom decimal(12,2). */
    private const MAX_PRICE = 9999999999;

    /**
     * Hak akses sudah dibatasi middleware role:admin pada rute.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'garage_type' => ['required', Rule::enum(GarageType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.GarageSequence::MAX_NUMBER],
            'daily_price' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_PRICE],
            'kwh_price' => [
                'nullable',
                'required_if:garage_type,'.GarageType::ELECTRIC_CAR->value,
                'prohibited_unless:garage_type,'.GarageType::ELECTRIC_CAR->value,
                'numeric', 'gt:0', 'max:'.self::MAX_PRICE,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kwh_price.required_if' => 'Harga isi listrik per kWh wajib diisi untuk garasi mobil listrik.',
            'kwh_price.prohibited_unless' => 'Harga isi listrik hanya diisi untuk garasi mobil listrik.',
        ];
    }

    /**
     * Tolak jumlah yang melebihi sisa kode tipe garasi (BR-05.3) sebelum mencoba menyimpan.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['garage_type', 'quantity'])) {
                    return;
                }

                $type = $this->garageType();
                $remaining = app(GarageService::class)->remainingCapacity($type);

                if ((int) $this->input('quantity') > $remaining) {
                    $validator->errors()->add('quantity', "Sisa kode garasi {$type->label()} hanya {$remaining}.");
                }
            },
        ];
    }

    /**
     * Tipe garasi yang sudah tervalidasi.
     */
    public function garageType(): GarageType
    {
        return GarageType::from($this->input('garage_type'));
    }

    /**
     * Harga isi listrik per kWh, null bila bukan garasi mobil listrik.
     */
    public function kwhPrice(): ?float
    {
        return $this->filled('kwh_price') ? (float) $this->input('kwh_price') : null;
    }
}
