@php use App\Enums\RentalType; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Riwayat Sewa</h2>
            @if (Auth::user()->isApprovedRenter())
                <a href="{{ route('renter.rentals.create') }}" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Sewa Garasi Baru</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                @if ($rentals->isEmpty())
                    <p class="p-6 text-sm text-gray-500">Belum ada sewa.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Garasi</th>
                                <th class="px-4 py-3 font-medium">Kendaraan</th>
                                <th class="px-4 py-3 font-medium">Masa Sewa</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium text-right">Biaya Awal</th>
                                <th class="px-4 py-3 font-medium text-right">Perpanjangan</th>
                                <th class="px-4 py-3 font-medium text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rentals as $rental)
                                @php
                                    $today = today();
                                    $status = match (true) {
                                        $rental->end_date->lte($today) => ['Selesai', 'bg-gray-100 text-gray-700'],
                                        $rental->start_date->gt($today) => ['Akan datang', 'bg-blue-100 text-blue-800'],
                                        default => ['Berjalan', 'bg-green-100 text-green-800'],
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-semibold">{{ $rental->garage->code }}</div>
                                        <div class="text-xs text-gray-500">{{ $rental->garage->garage_type->label() }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $rental->vehicle->type->label() }}@if ($rental->vehicle->plate_number) · {{ $rental->vehicle->plate_number }}@endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $rental->start_date->translatedFormat('j M Y') }} – {{ $rental->end_date->copy()->subDay()->translatedFormat('j M Y') }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $rental->rental_type === RentalType::DAILY ? 'Harian' : 'Bulanan' }}, {{ (int) $rental->start_date->diffInDays($rental->end_date) }} hari ·
                                            dikembalikan {{ $rental->end_date->translatedFormat('j M Y') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $status[1] }}">{{ $status[0] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">@rupiah($rental->total_cost)</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($rental->extensions->isNotEmpty())
                                            @rupiah($rental->extensions->sum('extra_cost'))
                                            <div class="text-xs text-gray-500">{{ $rental->extensions->count() }}× perpanjangan</div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold">@rupiah($rental->totalWithExtensions())</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
