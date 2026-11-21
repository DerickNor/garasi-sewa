@php use App\Enums\GarageStatus; use App\Enums\GarageType; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Daftar Garasi</h2>
            @if (Route::has('admin.garages.create'))
                <a href="{{ route('admin.garages.create') }}" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Buat Garasi</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('admin.garages.index') }}" class="flex flex-wrap items-end gap-3 bg-white shadow-sm sm:rounded-lg p-4">
                <div>
                    <label for="type" class="block text-sm text-gray-600">Tipe garasi</label>
                    <select id="type" name="type" class="mt-1 rounded-md border-gray-300 text-sm">
                        <option value="">Semua tipe</option>
                        @foreach (GarageType::cases() as $type)
                            <option value="{{ $type->value }}" @selected($selectedType === $type)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm text-gray-600">Status</label>
                    <select id="status" name="status" class="mt-1 rounded-md border-gray-300 text-sm">
                        <option value="">Semua status</option>
                        @foreach (GarageStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($selectedStatus === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Terapkan</button>
                <a href="{{ route('admin.garages.index') }}" class="text-sm text-gray-600 underline">Reset</a>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                @if ($garages->isEmpty())
                    <p class="p-6 text-sm text-gray-500">Belum ada garasi.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Kode</th>
                                <th class="px-4 py-3 font-medium">Tipe</th>
                                <th class="px-4 py-3 font-medium text-right">Harga Harian</th>
                                <th class="px-4 py-3 font-medium text-right">Harga Bulanan</th>
                                <th class="px-4 py-3 font-medium text-right">Isi Listrik / kWh</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($garages as $garage)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold">{{ $garage->code }}</td>
                                    <td class="px-4 py-3">{{ $garage->garage_type->label() }}</td>
                                    <td class="px-4 py-3 text-right">@rupiah($garage->daily_price)</td>
                                    <td class="px-4 py-3 text-right">@rupiah($garage->daily_price * \App\Services\RentalPricingService::MONTHLY_MULTIPLIER)</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($garage->kwh_price !== null)
                                            @rupiah($garage->kwh_price)
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-green-100 text-green-800' => $garage->status === GarageStatus::AVAILABLE,
                                            'bg-yellow-100 text-yellow-800' => $garage->status === GarageStatus::USED,
                                        ])>{{ $garage->status->label() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="p-4">{{ $garages->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
