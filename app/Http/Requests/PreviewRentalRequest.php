<?php

namespace App\Http\Requests;

/**
 * Validasi untuk menghitung rincian biaya sebelum menyimpan: sama dengan form sewa baru tanpa bukti pembayaran.
 */
class PreviewRentalRequest extends StoreRentalRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['payment_proof']);

        return $rules;
    }
}
