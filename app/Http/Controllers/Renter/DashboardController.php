<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman awal penyewa, termasuk informasi status verifikasi akun (REQ-VER-03).
 */
class DashboardController extends Controller
{
    /**
     * Tampilkan dashboard penyewa.
     */
    public function index(Request $request): View
    {
        return view('renter.dashboard', ['user' => $request->user()]);
    }
}
