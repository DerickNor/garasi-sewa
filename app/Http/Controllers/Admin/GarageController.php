<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGarageRequest;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Manajemen garasi oleh admin: daftar garasi (REQ-GAR-02) dan buat garasi berkelompok (REQ-GAR-01).
 */
class GarageController extends Controller
{
    /** Jumlah garasi per halaman. */
    private const PER_PAGE = 50;

    /**
     * Daftar garasi, bisa difilter berdasarkan tipe dan status.
     */
    public function index(Request $request): View
    {
        $type = GarageType::tryFrom((string) $request->query('type'));
        $status = GarageStatus::tryFrom((string) $request->query('status'));

        $garages = Garage::query()
            ->when($type, fn ($query) => $query->where('garage_type', $type))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('code')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.garages.index', [
            'garages' => $garages,
            'selectedType' => $type,
            'selectedStatus' => $status,
        ]);
    }

    /**
     * Form buat garasi berkelompok beserta kode berikutnya untuk tiap tipe.
     */
    public function create(GarageService $garages): View
    {
        $nextCodes = [];
        foreach (GarageType::cases() as $type) {
            $nextCodes[$type->value] = $garages->remainingCapacity($type) > 0
                ? $garages->nextCodes($type, 1)[0]
                : null;
        }

        return view('admin.garages.create', ['nextCodes' => $nextCodes]);
    }

    /**
     * Simpan garasi berkelompok lalu kembali ke daftar garasi.
     */
    public function store(StoreGarageRequest $request, GarageService $garages): RedirectResponse
    {
        $type = $request->garageType();

        try {
            $created = $garages->createGarages(
                $type,
                (int) $request->validated('quantity'),
                (float) $request->validated('daily_price'),
                $request->kwhPrice(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        $range = $created->count() > 1
            ? "{$created->first()->code}–{$created->last()->code}"
            : $created->first()->code;

        return redirect()->route('admin.garages.index', ['type' => $type->value])
            ->with('status', "{$created->count()} garasi {$type->label()} berhasil dibuat: {$range}.");
    }
}
