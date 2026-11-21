<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manajemen garasi oleh admin: daftar garasi (REQ-GAR-02).
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
}
