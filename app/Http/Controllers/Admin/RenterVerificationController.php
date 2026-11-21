<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Verifikasi pendaftar baru: admin mengecek data KTP lalu menerima atau menolak (REQ-VER-01, REQ-VER-02).
 *
 * Mengubah User secara langsung karena hanya mengganti status tanpa aturan hitung (design.md bagian 6).
 */
class RenterVerificationController extends Controller
{
    /**
     * Daftar penyewa yang menunggu verifikasi, yang paling lama mendaftar di atas.
     */
    public function index(): View
    {
        $renters = User::where('role', UserRole::PENYEWA)
            ->where('verification_status', VerificationStatus::PENDING)
            ->oldest()
            ->get();

        return view('admin.renters.index', ['renters' => $renters]);
    }

    /**
     * Terima pendaftaran: status approved dan waktu verifikasi dicatat.
     */
    public function approve(User $user): RedirectResponse
    {
        if (! $this->isPendingRenter($user)) {
            return $this->alreadyProcessed();
        }

        $user->verification_status = VerificationStatus::APPROVED;
        $user->verified_at = now();
        $user->save();

        return redirect()->route('admin.renters.index')
            ->with('status', "Pendaftaran {$user->name} diterima.");
    }

    /**
     * Tolak pendaftaran: status rejected.
     */
    public function reject(User $user): RedirectResponse
    {
        if (! $this->isPendingRenter($user)) {
            return $this->alreadyProcessed();
        }

        $user->verification_status = VerificationStatus::REJECTED;
        $user->verified_at = null;
        $user->save();

        return redirect()->route('admin.renters.index')
            ->with('status', "Pendaftaran {$user->name} ditolak.");
    }

    private function isPendingRenter(User $user): bool
    {
        return $user->role === UserRole::PENYEWA
            && $user->verification_status === VerificationStatus::PENDING;
    }

    private function alreadyProcessed(): RedirectResponse
    {
        return redirect()->route('admin.renters.index')
            ->with('error', 'Pengguna ini bukan pendaftar yang menunggu verifikasi.');
    }
}
