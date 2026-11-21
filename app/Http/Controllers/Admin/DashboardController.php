<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\User;
use Illuminate\View\View;

/**
 * Halaman awal admin berisi ringkasan singkat.
 */
class DashboardController extends Controller
{
    /**
     * Tampilkan dashboard admin.
     */
    public function index(): View
    {
        return view('admin.dashboard', [
            'pendingRenters' => User::where('role', UserRole::PENYEWA)
                ->where('verification_status', VerificationStatus::PENDING)
                ->count(),
            'garageCount' => Garage::count(),
        ]);
    }
}
