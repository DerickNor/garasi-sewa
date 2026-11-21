<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\User;
use App\Services\RentalService;
use App\Services\ReportService;
use Illuminate\View\View;

/**
 * Halaman awal admin berisi ringkasan singkat.
 */
class DashboardController extends Controller
{
    /**
     * Tampilkan dashboard admin: pendaftar menunggu, jumlah garasi, sewa aktif, dan pendapatan bulan ini.
     */
    public function index(RentalService $rentals, ReportService $reports): View
    {
        $today = today();

        return view('admin.dashboard', [
            'pendingRenters' => User::where('role', UserRole::PENYEWA)
                ->where('verification_status', VerificationStatus::PENDING)
                ->count(),
            'garageCount' => Garage::count(),
            'activeRentals' => $rentals->activeRentals($today)->count(),
            'monthRevenue' => $reports->monthlyInYear($today->year)[$today->month - 1]['revenue'],
            'monthName' => $today->translatedFormat('F Y'),
        ]);
    }
}
