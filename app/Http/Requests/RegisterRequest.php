<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi pendaftaran penyewa (REQ-AUTH-01, design.md bagian 10).
 */
class RegisterRequest extends FormRequest
{
    /**
     * Pendaftaran terbuka untuk semua pengunjung (rute dibatasi middleware guest).
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
            'nik' => ['required', 'digits:16', 'unique:'.User::class],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:8,15'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
