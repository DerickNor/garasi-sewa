<?php

namespace App\Http\Controllers\Renter;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewRentalRequest;
use App\Http\Requests\StoreRentalRequest;
use App\Models\Garage;
use App\Services\GarageService;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Sewa garasi oleh penyewa: riwayat, form sewa baru, garasi tersedia, rincian biaya, dan simpan (REQ-RENT).
 */
class RentalController extends Controller
{
    /**
     * Riwayat sewa milik penyewa yang sedang login (REQ-RENT-09).
     */
    public function index(Request $request): View
    {
        $rentals = $request->user()->rentals()
            ->with(['garage', 'vehicle', 'extensions'])
            ->latest('start_date')
            ->latest('id')
            ->get();

        return view('renter.rentals.index', ['rentals' => $rentals]);
    }

    /**
     * Form sewa baru (REQ-RENT-01).
     */
    public function create(): View
    {
        $today = today();

        return view('renter.rentals.create', [
            'vehicleTypes' => VehicleType::cases(),
            'garageTypeLabels' => collect(GarageType::cases())->mapWithKeys(fn (GarageType $type) => [
                $type->value => $type->label(),
            ]),
            'defaultGarageTypes' => collect(VehicleType::cases())->mapWithKeys(fn (VehicleType $type) => [
                $type->value => $type->defaultGarageType()->value,
            ]),
            'minStartDate' => $today->toDateString(),
            'maxStartDate' => $today->copy()->addDays(RentalService::MAX_ADVANCE_DAYS)->toDateString(),
        ]);
    }

    /**
     * Periode sewa (tanggal selesai otomatis) dan daftar garasi yang kosong untuk seluruh periode tersebut
     * (REQ-RENT-04, REQ-RENT-05). Bila tipe garasi belum dipilih, hanya periodenya yang dikembalikan.
     */
    public function availableGarages(Request $request, RentalPricingService $pricing, GarageService $garages): JsonResponse
    {
        $today = today();
        $input = $request->validate([
            'garage_type' => ['nullable', Rule::enum(GarageType::class)],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'days' => ['exclude_unless:rental_type,'.RentalType::DAILY->value, 'required', 'integer', 'min:1'],
            'start_date' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$today->copy()->addDays(RentalService::MAX_ADVANCE_DAYS)->toDateString(),
            ],
        ]);

        $start = CarbonImmutable::parse($input['start_date']);

        try {
            $duration = $pricing->durationDays(RentalType::from($input['rental_type']), $input['days'] ?? null, $start);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['days' => $e->getMessage()]);
        }

        $end = $start->addDays($duration);
        $garageType = isset($input['garage_type']) ? GarageType::from($input['garage_type']) : null;

        return response()->json([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'last_day' => $end->subDay()->toDateString(),
            'duration_days' => $duration,
            'max_daily_days' => $pricing->maxDailyDays($start),
            'garages' => $garageType === null ? [] : $garages->availableGarages($garageType, $start, $end)
                ->map(fn (Garage $garage) => [
                    'id' => $garage->id,
                    'code' => $garage->code,
                    'daily_price' => (float) $garage->daily_price,
                    'kwh_price' => $garage->kwh_price !== null ? (float) $garage->kwh_price : null,
                ]),
        ]);
    }

    /**
     * Rincian biaya sewa yang akan dibayar, dihitung server (REQ-RENT-06).
     */
    public function preview(PreviewRentalRequest $request, RentalService $rentals): JsonResponse
    {
        try {
            $price = $rentals->previewRental($request->validated());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['garage_id' => $e->getMessage()]);
        }

        return response()->json([
            'duration_days' => $price->durationDays,
            'end_date' => $price->endDate->toDateString(),
            'last_day' => $price->endDate->subDay()->toDateString(),
            'rental_fee' => $price->rentalFee,
            'charging_fee' => $price->chargingFee,
            'total_cost' => $price->totalCost,
        ]);
    }

    /**
     * Simpan sewa baru lalu tampilkan riwayat sewa (REQ-RENT-08).
     */
    public function store(StoreRentalRequest $request, RentalService $rentals): RedirectResponse
    {
        try {
            $rental = $rentals->createRental($request->user(), $request->validated(), $request->file('payment_proof'));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('renter.rentals.index')
            ->with('status', "Sewa garasi {$rental->garage->code} berhasil disimpan.");
    }
}
