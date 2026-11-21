<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRentalExtensionRequest;
use App\Models\Rental;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Perpanjangan sewa oleh admin (REQ-EXT-01..04).
 */
class RentalExtensionController extends Controller
{
    /**
     * Daftar sewa yang belum berakhir (REQ-EXT-01).
     */
    public function index(RentalService $rentals): View
    {
        return view('admin.extensions.index', ['rentals' => $rentals->activeRentals(today())]);
    }

    /**
     * Form perpanjangan untuk satu sewa (REQ-EXT-02).
     */
    public function create(Rental $rental): View|RedirectResponse
    {
        if ($rental->end_date->lte(today())) {
            return redirect()->route('admin.extensions.index')
                ->with('error', 'Sewa ini sudah berakhir sehingga tidak dapat diperpanjang.');
        }

        $rental->load(['garage', 'user', 'vehicle', 'extensions']);

        return view('admin.extensions.create', ['rental' => $rental]);
    }

    /**
     * Rincian bulan penuh, sisa hari, dan kurang bayar untuk tanggal selesai baru (REQ-EXT-03).
     */
    public function preview(Request $request, Rental $rental, RentalService $rentals): JsonResponse
    {
        $input = $request->validate(
            ['new_end_date' => StoreRentalExtensionRequest::newEndDateRules($rental)],
            StoreRentalExtensionRequest::newEndDateMessages($rental),
        );
        $newEnd = CarbonImmutable::parse($input['new_end_date']);

        try {
            $extension = $rentals->previewExtension($rental, $newEnd);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['new_end_date' => $e->getMessage()]);
        }

        return response()->json([
            'months' => $extension->months,
            'days' => $extension->days,
            'cost' => $extension->cost,
            'last_day' => $newEnd->subDay()->toDateString(),
        ]);
    }

    /**
     * Simpan perpanjangan beserta bukti pembayaran (REQ-EXT-04).
     */
    public function store(StoreRentalExtensionRequest $request, Rental $rental, RentalService $rentals): RedirectResponse
    {
        try {
            $extension = $rentals->extendRental(
                $rental,
                CarbonImmutable::parse($request->validated('new_end_date')),
                $request->user(),
                $request->file('payment_proof'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $cost = 'Rp'.number_format((float) $extension->extra_cost, 0, ',', '.');

        return redirect()->route('admin.extensions.index')->with(
            'status',
            "Sewa garasi {$rental->garage->code} diperpanjang sampai "
                .$extension->new_end_date->translatedFormat('j F Y').". Kurang bayar {$cost}.",
        );
    }
}
