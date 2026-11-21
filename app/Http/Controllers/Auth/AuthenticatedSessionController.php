<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Masuk dan keluar aplikasi (REQ-AUTH-02). Email dipakai sebagai username.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Tampilkan form masuk.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Proses masuk. Setelah berhasil, /dashboard mengarahkan admin dan penyewa ke halamannya masing-masing.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Buat ulang ID sesi untuk mencegah session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Keluar: akhiri sesi dan buat token CSRF baru.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
