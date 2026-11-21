<?php

namespace App\Http\Requests;

use App\Models\Rental;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form perpanjangan sewa (REQ-EXT-02, REQ-EXT-04, design.md bagian 10).
 *
 * Aturan sewa masih berjalan dan tidak bertabrakan diperiksa RentalService di dalam transaksi.
 */
class StoreRentalExtensionRequest extends FormRequest
{
    /** Ukuran maksimum bukti pembayaran dalam kilobyte (ASM-08). */
    private const MAX_PROOF_KB = 2048;

    /**
     * Hak akses sudah dibatasi middleware role:admin pada rute.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan tanggal selesai baru untuk $rental; dipakai juga oleh endpoint preview.
     *
     * @return array<int, string>
     */
    public static function newEndDateRules(Rental $rental): array
    {
        return ['required', 'date_format:Y-m-d', 'after:'.$rental->end_date->toDateString()];
    }

    /**
     * Pesan untuk aturan tanggal selesai baru.
     *
     * @return array<string, string>
     */
    public static function newEndDateMessages(Rental $rental): array
    {
        return [
            'new_end_date.after' => 'Tanggal selesai baru harus setelah tanggal selesai sekarang ('
                .$rental->end_date->translatedFormat('j F Y').').',
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_end_date' => self::newEndDateRules($this->route('rental')),
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PROOF_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::newEndDateMessages($this->route('rental')) + [
            'payment_proof.max' => 'Bukti pembayaran maksimal 2 MB.',
        ];
    }
}
