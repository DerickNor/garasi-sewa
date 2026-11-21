<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Verifikasi bukti pembayaran sewa baru oleh admin sebelum sewa berlangsung (REQ-PAY-01, REQ-PAY-02, BR-12).
 *
 * Perpanjangan tidak diverifikasi di sini karena bukti pembayarannya diunggah langsung oleh admin.
 */
class PaymentVerificationController extends Controller
{
    /**
     * Daftar sewa yang menunggu verifikasi pembayaran.
     */
    public function index(RentalService $rentals): View
    {
        return view('admin.payments.index', ['rentals' => $rentals->pendingPayments()]);
    }

    /**
     * Terima pembayaran: sewa mulai berlangsung.
     */
    public function approve(Request $request, Rental $rental, RentalService $rentals): RedirectResponse
    {
        try {
            $rentals->approvePayment($rental, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.payments.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payments.index')
            ->with('status', "Pembayaran sewa garasi {$rental->garage->code} oleh {$rental->user->name} diterima.");
    }

    /**
     * Tolak pembayaran: sewa batal dan garasi dapat disewa lagi.
     */
    public function reject(Request $request, Rental $rental, RentalService $rentals): RedirectResponse
    {
        try {
            $rentals->rejectPayment($rental, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.payments.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payments.index')
            ->with('status', "Pembayaran sewa garasi {$rental->garage->code} oleh {$rental->user->name} ditolak.");
    }
}
