<?php

namespace App\Services;

use App\Enums\GarageType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan hasil persewaan garasi (REQ-REP-01..04).
 *
 * Setiap baris berisi jumlah sewa dan pendapatan. Sewa dikelompokkan berdasarkan tanggal mulai, dan
 * pendapatan = total_cost (biaya sewa + isi listrik) + seluruh biaya perpanjangan sewa itu (ASM-09).
 *
 * Bentuk baris: ['key' => mixed, 'label' => string, 'detail' => ?string, 'rentals' => int, 'revenue' => float].
 */
class ReportService
{
    /** Jumlah bulan dalam satu tahun. */
    private const MONTHS_IN_YEAR = 12;

    /**
     * Laporan per bulan dalam tahun $year: selalu 12 baris, bulan tanpa sewa bernilai 0 (REQ-REP-01).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function monthlyInYear(int $year): Collection
    {
        $data = $this->baseQuery()
            ->whereYear('rentals.start_date', $year)
            ->selectRaw('MONTH(rentals.start_date) as bucket')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        return collect(range(1, self::MONTHS_IN_YEAR))->map(fn (int $month) => $this->row(
            $month,
            CarbonImmutable::create($year, $month, 1)->translatedFormat('F'),
            null,
            $data->get($month),
        ));
    }

    /**
     * Laporan per hari dalam bulan $month tahun $year: satu baris per hari kalender (REQ-REP-02).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dailyInMonth(int $month, int $year): Collection
    {
        $firstDay = CarbonImmutable::create($year, $month, 1);

        $data = $this->baseQuery()
            ->whereYear('rentals.start_date', $year)
            ->whereMonth('rentals.start_date', $month)
            ->selectRaw('DAY(rentals.start_date) as bucket')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        return collect(range(1, $firstDay->daysInMonth))->map(function (int $day) use ($firstDay, $data) {
            $date = $firstDay->setDay($day);

            return $this->row($date->toDateString(), $date->translatedFormat('j M Y'), $date->translatedFormat('l'), $data->get($day));
        });
    }

    /**
     * Laporan per tipe garasi: baris Sepeda, Mobil, Mobil Listrik (REQ-REP-03).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byGarageType(?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $data = $this->filtered($from, $to)
            ->join('garages', 'garages.id', '=', 'rentals.garage_id')
            ->addSelect('garages.garage_type as bucket')
            ->groupBy('garages.garage_type')
            ->get()
            ->keyBy('bucket');

        return collect(GarageType::cases())->map(fn (GarageType $type) => $this->row(
            $type->value,
            $type->label(),
            "Kode {$type->prefix()}",
            $data->get($type->value),
        ));
    }

    /**
     * Laporan per penyewa: satu baris per penyewa yang punya sewa, urut pendapatan terbesar (REQ-REP-04).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byRenter(?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        return $this->filtered($from, $to)
            ->join('users', 'users.id', '=', 'rentals.user_id')
            ->addSelect('users.id', 'users.name', 'users.email')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('revenue')
            ->orderBy('users.name')
            ->get()
            ->map(fn (object $result) => $this->row($result->id, $result->name, $result->email, $result))
            ->values();
    }

    /**
     * Query dasar: jumlah sewa dan pendapatan (total_cost + total perpanjangan per sewa).
     */
    private function baseQuery(): Builder
    {
        $extensions = DB::table('rental_extensions')
            ->select('rental_id')
            ->selectRaw('SUM(extra_cost) as extension_total')
            ->groupBy('rental_id');

        return DB::table('rentals')
            ->leftJoinSub($extensions, 'extensions', 'extensions.rental_id', '=', 'rentals.id')
            ->selectRaw('COUNT(*) as rentals')
            ->selectRaw('SUM(rentals.total_cost + COALESCE(extensions.extension_total, 0)) as revenue');
    }

    /**
     * Query dasar dengan filter tanggal mulai opsional (keduanya inklusif).
     */
    private function filtered(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return $this->baseQuery()
            ->when($from, fn (Builder $query) => $query->whereDate('rentals.start_date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->whereDate('rentals.start_date', '<=', $to->toDateString()));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(mixed $key, string $label, ?string $detail, ?object $result): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => $detail,
            'rentals' => (int) ($result->rentals ?? 0),
            'revenue' => (float) ($result->revenue ?? 0),
        ];
    }
}
