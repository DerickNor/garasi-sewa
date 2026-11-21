<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validasi dan proses masuk, termasuk pembatasan percobaan masuk yang gagal (REQ-AUTH-02).
 */
class LoginRequest extends FormRequest
{
    /** Jumlah percobaan masuk gagal sebelum dikunci sementara. */
    private const MAX_ATTEMPTS = 5;

    /** Jumlah detik dalam satu menit, untuk pesan waktu tunggu. */
    private const SECONDS_PER_MINUTE = 60;

    /**
     * Form masuk terbuka untuk semua pengunjung (rute dibatasi middleware guest).
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi form masuk.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Coba masuk dengan email dan password. Pesan gagal sengaja umum agar tidak membocorkan
     * apakah email terdaftar (REQ-AUTH-02.2).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Tolak sementara bila sudah terlalu banyak percobaan masuk gagal dari email dan IP yang sama.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / self::SECONDS_PER_MINUTE),
            ]),
        ]);
    }

    /**
     * Kunci pembatasan percobaan masuk: kombinasi email dan alamat IP.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
