@php use App\Enums\RentalType; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verifikasi Pembayaran Sewa</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                @if ($rentals->isEmpty())
                    <p class="p-6 text-sm text-gray-500">Tidak ada pembayaran yang menunggu verifikasi.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Garasi</th>
                                <th class="px-4 py-3 font-medium">Penyewa</th>
                                <th class="px-4 py-3 font-medium">Kendaraan</th>
                                <th class="px-4 py-3 font-medium">Masa Sewa</th>
                                <th class="px-4 py-3 font-medium text-right">Total</th>
                                <th class="px-4 py-3 font-medium">Bukti</th>
                                <th class="px-4 py-3 font-medium text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rentals as $rental)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-semibold">{{ $rental->garage->code }}</div>
                                        <div class="text-xs text-gray-500">{{ $rental->garage->garage_type->label() }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $rental->user->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $rental->user->phone }}</div>
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
                                            {{ $rental->rental_type === RentalType::DAILY ? 'Harian' : 'Bulanan' }}, {{ $rental->duration_days }} hari ·
                                            diajukan {{ $rental->created_at->format('d/m/Y H:i') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold">@rupiah($rental->total_cost)</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ Storage::disk('public')->url($rental->payment_proof_path) }}" target="_blank" class="text-indigo-600 hover:underline">Lihat bukti</a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.payments.approve', $rental) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">Terima</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.payments.reject', $rental) }}"
                                                  onsubmit="return confirm('Tolak pembayaran sewa {{ $rental->garage->code }}? Sewa akan dibatalkan.')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Tolak</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
