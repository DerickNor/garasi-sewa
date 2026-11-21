<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Laporan sewa garasi untuk admin (REQ-REP-01..04).
 */
class ReportController extends Controller
{
    /** Rentang tahun yang boleh dipilih pada filter laporan. */
    private const MIN_YEAR = 2000;

    private const MAX_YEAR = 2100;

    /**
     * @param  ReportService  $reports  penghitung data laporan
     */
    public function __construct(private readonly ReportService $reports) {}

    /**
     * Laporan per bulan dalam tahun tertentu (REQ-REP-01).
     */
    public function monthly(Request $request): View
    {
        $input = $request->validate(['year' => $this->yearRule()]);
        $year = (int) ($input['year'] ?? today()->year);

        return $this->show('monthly', "Laporan Sewa per Bulan Tahun {$year}", 'Bulan',
            $this->reports->monthlyInYear($year), ['year' => $year]);
    }

    /**
     * Laporan per hari dalam bulan tertentu (REQ-REP-02).
     */
    public function daily(Request $request): View
    {
        $input = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => $this->yearRule(),
        ]);
        $month = (int) ($input['month'] ?? today()->month);
        $year = (int) ($input['year'] ?? today()->year);
        $title = 'Laporan Sewa per Hari '.CarbonImmutable::create($year, $month, 1)->translatedFormat('F Y');

        return $this->show('daily', $title, 'Tanggal',
            $this->reports->dailyInMonth($month, $year), ['month' => $month, 'year' => $year]);
    }

    /**
     * Laporan per tipe garasi (REQ-REP-03).
     */
    public function byType(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        return $this->show('by-type', 'Laporan Sewa per Tipe Garasi', 'Tipe Garasi',
            $this->reports->byGarageType($from, $to), ['from' => $from, 'to' => $to]);
    }

    /**
     * Laporan per penyewa (REQ-REP-04).
     */
    public function byRenter(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        return $this->show('by-renter', 'Laporan Sewa per Penyewa', 'Penyewa',
            $this->reports->byRenter($from, $to), ['from' => $from, 'to' => $to]);
    }

    /**
     * @return array<int, string>
     */
    private function yearRule(): array
    {
        return ['nullable', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR];
    }

    /**
     * Filter rentang tanggal mulai sewa yang boleh dikosongkan.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function dateRange(Request $request): array
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        return [
            isset($input['from']) ? CarbonImmutable::parse($input['from']) : null,
            isset($input['to']) ? CarbonImmutable::parse($input['to']) : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function show(string $report, string $title, string $labelHeader, Collection $rows, array $filters): View
    {
        return view('admin.reports.show', [
            'report' => $report,
            'title' => $title,
            'labelHeader' => $labelHeader,
            'rows' => $rows,
            'filters' => $filters,
            'totalRentals' => $rows->sum('rentals'),
            'totalRevenue' => (float) $rows->sum('revenue'),
        ]);
    }
}
