<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Pendaftaran penyewa baru (REQ-AUTH-01).
 */
class RegisteredUserController extends Controller
{
    /**
     * Tampilkan form pendaftaran.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Simpan penyewa baru berstatus pending, lalu arahkan ke dashboard penyewa.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = new User($request->validated());
        $user->role = UserRole::PENYEWA;
        $user->verification_status = VerificationStatus::PENDING;
        $user->save();

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('renter.dashboard')
            ->with('status', 'Pendaftaran berhasil. Akun Anda menunggu verifikasi admin.');
    }
}
