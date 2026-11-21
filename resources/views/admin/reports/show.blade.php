<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Sewa Garasi</h2>
    </x-slot>

    @php
        $tabs = [
            'monthly' => ['Per Bulan', 'admin.reports.monthly'],
            'daily' => ['Per Hari', 'admin.reports.daily'],
            'by-type' => ['Per Tipe Garasi', 'admin.reports.by-type'],
            'by-renter' => ['Per Penyewa', 'admin.reports.by-renter'],
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <nav class="flex flex-wrap gap-2">
                @foreach ($tabs as $key => [$label, $route])
                    <a href="{{ route($route) }}" @class([
                        'rounded-md px-4 py-2 text-sm font-medium',
                        'bg-indigo-600 text-white' => $report === $key,
                        'bg-white text-gray-700 shadow-sm hover:bg-gray-50' => $report !== $key,
                    ])>{{ $label }}</a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route($tabs[$report][1]) }}" class="flex flex-wrap items-end gap-3 bg-white shadow-sm sm:rounded-lg p-4">
                @if ($report === 'daily')
                    <div>
                        <label for="month" class="block text-sm text-gray-600">Bulan</label>
                        <select id="month" name="month" class="mt-1 rounded-md border-gray-300 text-sm">
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}" @selected($filters['month'] === $month)>
                                    {{ \Carbon\CarbonImmutable::create(2000, $month, 1)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (in_array($report, ['monthly', 'daily'], true))
                    <div>
                        <label for="year" class="block text-sm text-gray-600">Tahun</label>
                        <input id="year" name="year" type="number" min="2000" max="2100" value="{{ $filters['year'] }}"
                               class="mt-1 w-28 rounded-md border-gray-300 text-sm">
                    </div>
                @else
                    <div>
                        <label for="from" class="block text-sm text-gray-600">Tanggal mulai sewa dari</label>
                        <input id="from" name="from" type="date" value="{{ $filters['from']?->toDateString() }}" class="mt-1 rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-sm text-gray-600">sampai</label>
                        <input id="to" name="to" type="date" value="{{ $filters['to']?->toDateString() }}" class="mt-1 rounded-md border-gray-300 text-sm">
                    </div>
                @endif

                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Tampilkan</button>

                @if ($errors->any())
                    <div class="w-full text-sm text-red-600">{{ $errors->first() }}</div>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <div class="px-4 py-3 border-b">
                    <h3 class="font-semibold text-gray-800">{{ $title }}</h3>
                    <p class="text-xs text-gray-500">Sewa dihitung berdasarkan tanggal mulai. Pendapatan = biaya sewa + biaya isi listrik + perpanjangan.</p>
                </div>

                @if ($rows->isEmpty())
                    <p class="p-6 text-sm text-gray-500">Belum ada data sewa.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ $labelHeader }}</th>
                                <th class="px-4 py-3 font-medium text-right">Jumlah Sewa</th>
                                <th class="px-4 py-3 font-medium text-right">Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                <tr @class(['text-gray-400' => $row['rentals'] === 0])>
                                    <td class="px-4 py-2">
                                        {{ $row['label'] }}
                                        @if ($row['detail'])
                                            <span class="block text-xs text-gray-500">{{ $row['detail'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ $row['rentals'] }}</td>
                                    <td class="px-4 py-2 text-right">@rupiah($row['revenue'])</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold text-gray-900">
                            <tr>
                                <td class="px-4 py-3">Total</td>
                                <td class="px-4 py-3 text-right">{{ $totalRentals }}</td>
                                <td class="px-4 py-3 text-right">@rupiah($totalRevenue)</td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
