@php use App\Enums\GarageType; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Buat Garasi Berkelompok</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.garages.store') }}"
                  x-data="{ type: @js(old('garage_type', '')) }"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                @csrf

                <div>
                    <x-input-label for="garage_type" value="Tipe garasi" />
                    <select id="garage_type" name="garage_type" x-model="type" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Pilih tipe garasi</option>
                        @foreach (GarageType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('garage_type') === $type->value)>
                                {{ $type->label() }} — kode berikutnya {{ $nextCodes[$type->value] ?? 'habis (sudah 999)' }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('garage_type')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="quantity" value="Jumlah garasi (maksimum 999)" />
                    <x-text-input id="quantity" name="quantity" type="number" min="1" max="999" class="mt-1 block w-full"
                                  :value="old('quantity')" required />
                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="daily_price" value="Harga sewa harian (Rp)" />
                    <x-text-input id="daily_price" name="daily_price" type="number" min="1" step="any" class="mt-1 block w-full"
                                  :value="old('daily_price')" required />
                    <p class="mt-1 text-xs text-gray-500">Harga sewa bulanan otomatis 25 × harga harian.</p>
                    <x-input-error :messages="$errors->get('daily_price')" class="mt-2" />
                </div>

                <div x-show="type === '{{ GarageType::ELECTRIC_CAR->value }}'" x-cloak>
                    <x-input-label for="kwh_price" value="Harga isi listrik per kWh (Rp)" />
                    <x-text-input id="kwh_price" name="kwh_price" type="number" min="1" step="any" class="mt-1 block w-full"
                                  :value="old('kwh_price')"
                                  x-bind:disabled="type !== '{{ GarageType::ELECTRIC_CAR->value }}'"
                                  x-bind:required="type === '{{ GarageType::ELECTRIC_CAR->value }}'" />
                    <x-input-error :messages="$errors->get('kwh_price')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('admin.garages.index') }}" class="text-sm text-gray-600 underline">Batal</a>
                    <x-primary-button>Buat Garasi</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
