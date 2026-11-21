@php use App\Enums\RentalType; use App\Enums\VehicleType; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sewa Garasi Baru</h2>
    </x-slot>

    <script>
        /**
         * State form sewa baru: menyesuaikan isian dengan tipe kendaraan, mengambil garasi tersedia
         * dan rincian biaya dari server (semua perhitungan dilakukan server).
         */
        function rentalForm(config) {
            return {
                vehicleType: config.old.vehicle_type ?? '',
                rentalType: config.old.rental_type ?? 'daily',
                days: config.old.days ?? 1,
                startDate: config.old.start_date ?? config.minStartDate,
                garageType: config.old.garage_type ?? '',
                garageId: config.old.garage_id ? String(config.old.garage_id) : '',
                batteryCapacity: config.old.battery_capacity_kwh ?? '',
                batteryStart: config.old.battery_start_kwh ?? '',
                period: null,
                garages: [],
                garageMessage: 'Lengkapi tipe sewa, tanggal mulai, dan tipe garasi untuk melihat garasi yang tersedia.',
                loadingGarages: false,
                price: null,
                priceMessage: '',

                get requiresPlate() { return this.vehicleType !== '' && this.vehicleType !== config.bicycle; },
                get isElectric() { return this.vehicleType === config.electricCar; },
                get isDaily() { return this.rentalType === config.daily; },
                get allowedGarageTypes() { return config.allowedGarageTypes[this.vehicleType] ?? []; },

                init() {
                    this.refreshGarages();
                },

                onVehicleChange() {
                    if (!this.allowedGarageTypes.includes(this.garageType)) {
                        this.garageType = this.allowedGarageTypes.length === 1 ? this.allowedGarageTypes[0] : '';
                    }
                    this.refreshGarages();
                },

                async refreshGarages() {
                    this.price = null;
                    if (!this.garageType || !this.startDate || (this.isDaily && !this.days)) {
                        this.period = null;
                        this.garages = [];
                        return;
                    }

                    const params = new URLSearchParams({
                        garage_type: this.garageType,
                        rental_type: this.rentalType,
                        start_date: this.startDate,
                    });
                    if (this.isDaily) params.append('days', this.days);

                    this.loadingGarages = true;
                    try {
                        const response = await fetch(`${config.availableUrl}?${params}`, { headers: { Accept: 'application/json' } });
                        const body = await response.json();

                        if (!response.ok) {
                            this.period = null;
                            this.garages = [];
                            this.garageMessage = Object.values(body.errors ?? {}).flat()[0] ?? 'Data belum valid.';
                            return;
                        }

                        this.period = body;
                        this.garages = body.garages;
                        if (!this.garages.some(garage => String(garage.id) === this.garageId)) this.garageId = '';
                        this.garageMessage = this.garages.length ? '' : 'Tidak ada garasi yang tersedia untuk tanggal tersebut.';
                        this.refreshPrice();
                    } finally {
                        this.loadingGarages = false;
                    }
                },

                async refreshPrice() {
                    this.price = null;
                    this.priceMessage = '';
                    if (!this.garageId) return;

                    const data = new FormData(this.$root);
                    data.delete('payment_proof');

                    const response = await fetch(config.previewUrl, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                        body: data,
                    });
                    const body = await response.json();

                    if (response.ok) {
                        this.price = body;
                    } else {
                        this.priceMessage = Object.values(body.errors ?? {}).flat()[0] ?? 'Biaya belum dapat dihitung.';
                    }
                },

                rupiah(value) {
                    return 'Rp' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
                },

                date(value) {
                    return new Date(value + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
                },
            };
        }
    </script>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('renter.rentals.store') }}" enctype="multipart/form-data"
                  x-data="rentalForm(@js([
                      'old' => old(),
                      'minStartDate' => $minStartDate,
                      'allowedGarageTypes' => $allowedGarageTypes,
                      'bicycle' => VehicleType::BICYCLE->value,
                      'electricCar' => VehicleType::ELECTRIC_CAR->value,
                      'daily' => RentalType::DAILY->value,
                      'availableUrl' => route('renter.garages.available'),
                      'previewUrl' => route('renter.rentals.preview'),
                      'csrfToken' => csrf_token(),
                  ]))"
                  class="space-y-6">
                @csrf

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                        Periksa kembali isian yang ditandai merah.
                    </div>
                @endif

                {{-- 1. Kendaraan --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">1. Detail Kendaraan</h3>

                    <div>
                        <x-input-label for="vehicle_type" value="Tipe kendaraan" />
                        <select id="vehicle_type" name="vehicle_type" x-model="vehicleType" @change="onVehicleChange()" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Pilih tipe kendaraan</option>
                            @foreach ($vehicleTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('vehicle_type')" class="mt-2" />
                    </div>

                    <div x-show="requiresPlate" x-cloak>
                        <x-input-label for="plate_number" value="Nomor plat" />
                        <x-text-input id="plate_number" name="plate_number" class="mt-1 block w-full uppercase" placeholder="L 1234 AB"
                                      :value="old('plate_number')" x-bind:disabled="!requiresPlate" x-bind:required="requiresPlate" />
                        <x-input-error :messages="$errors->get('plate_number')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="brand" value="Merek" />
                            <x-text-input id="brand" name="brand" class="mt-1 block w-full" :value="old('brand')" required />
                            <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="model" value="Tipe" />
                            <x-text-input id="model" name="model" class="mt-1 block w-full" :value="old('model')" required />
                            <x-input-error :messages="$errors->get('model')" class="mt-2" />
                        </div>
                    </div>

                    <div x-show="isElectric" x-cloak class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="battery_capacity_kwh" value="Kapasitas baterai (kWh)" />
                            <x-text-input id="battery_capacity_kwh" name="battery_capacity_kwh" type="number" min="0.01" step="0.01"
                                          class="mt-1 block w-full" x-model="batteryCapacity" @change="refreshPrice()"
                                          x-bind:disabled="!isElectric" x-bind:required="isElectric" />
                            <x-input-error :messages="$errors->get('battery_capacity_kwh')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="battery_start_kwh" value="Isi baterai saat ini (kWh)" />
                            <x-text-input id="battery_start_kwh" name="battery_start_kwh" type="number" min="0" step="0.01"
                                          class="mt-1 block w-full" x-model="batteryStart" @change="refreshPrice()"
                                          x-bind:disabled="!isElectric" x-bind:required="isElectric" />
                            <x-input-error :messages="$errors->get('battery_start_kwh')" class="mt-2" />
                        </div>
                        <p class="sm:col-span-2 text-xs text-gray-500">Di garasi mobil listrik, baterai diisi hingga penuh dan biayanya ditambahkan ke total.</p>
                    </div>
                </section>

                {{-- 2. Waktu sewa --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">2. Waktu Sewa</h3>

                    <div class="flex gap-6">
                        @foreach (RentalType::cases() as $type)
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="rental_type" value="{{ $type->value }}" x-model="rentalType" @change="refreshGarages()"
                                       class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                {{ $type === RentalType::DAILY ? 'Harian' : 'Bulanan' }}
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('rental_type')" class="mt-2" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="start_date" value="Tanggal mulai" />
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                          min="{{ $minStartDate }}" max="{{ $maxStartDate }}"
                                          x-model="startDate" @change="refreshGarages()" required />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                        </div>
                        <div x-show="isDaily">
                            <x-input-label for="days" value="Jumlah hari" />
                            <x-text-input id="days" name="days" type="number" min="1" class="mt-1 block w-full"
                                          x-bind:max="period ? period.max_daily_days : null"
                                          x-model="days" @input.debounce.400ms="refreshGarages()"
                                          x-bind:disabled="!isDaily" x-bind:required="isDaily" />
                            <p class="mt-1 text-xs text-gray-500" x-show="period">Maksimal <span x-text="period?.max_daily_days"></span> hari untuk bulan ini.</p>
                            <x-input-error :messages="$errors->get('days')" class="mt-2" />
                        </div>
                    </div>

                    <div x-show="period" class="rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                        Masa sewa <strong x-text="period?.duration_days"></strong> hari:
                        <span x-text="period && date(period.start_date)"></span> s.d. <span x-text="period && date(period.last_day)"></span>.
                        Garasi dikembalikan pada <strong x-text="period && date(period.end_date)"></strong>.
                    </div>
                </section>

                {{-- 3. Garasi --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">3. Pilih Garasi</h3>

                    <div>
                        <x-input-label for="garage_type" value="Tipe garasi" />
                        <select id="garage_type" name="garage_type" x-model="garageType" @change="refreshGarages()" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Pilih tipe garasi</option>
                            @foreach ($garageTypes as $type)
                                <option value="{{ $type->value }}" x-show="allowedGarageTypes.includes('{{ $type->value }}')"
                                        x-bind:disabled="!allowedGarageTypes.includes('{{ $type->value }}')">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500" x-show="!vehicleType">Pilih tipe kendaraan terlebih dahulu.</p>
                        <x-input-error :messages="$errors->get('garage_type')" class="mt-2" />
                    </div>

                    <div>
                        <p class="text-sm text-gray-600" x-show="loadingGarages">Memuat garasi yang tersedia…</p>
                        <p class="text-sm text-gray-500" x-show="!loadingGarages && garageMessage" x-text="garageMessage"></p>

                        <div class="grid gap-3 sm:grid-cols-3" x-show="!loadingGarages && garages.length">
                            <template x-for="garage in garages" :key="garage.id">
                                <label class="flex cursor-pointer flex-col rounded-md border p-3 text-sm"
                                       :class="garageId === String(garage.id) ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200'">
                                    <span class="flex items-center gap-2">
                                        <input type="radio" name="garage_id" :value="garage.id" x-model="garageId" @change="refreshPrice()"
                                               class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="font-mono font-semibold" x-text="garage.code"></span>
                                    </span>
                                    <span class="mt-1 text-gray-600" x-text="rupiah(garage.daily_price) + ' / hari'"></span>
                                    <span class="text-gray-500 text-xs" x-show="garage.kwh_price !== null" x-text="rupiah(garage.kwh_price) + ' / kWh'"></span>
                                </label>
                            </template>
                        </div>
                        <x-input-error :messages="$errors->get('garage_id')" class="mt-2" />
                    </div>
                </section>

                {{-- 4. Biaya dan pembayaran --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">4. Biaya dan Bukti Pelunasan</h3>

                    <p class="text-sm text-gray-500" x-show="!price && !priceMessage">Pilih garasi untuk melihat total biaya.</p>
                    <p class="text-sm text-red-600" x-show="priceMessage" x-text="priceMessage"></p>

                    <dl class="text-sm" x-show="price">
                        <div class="flex justify-between py-1"><dt>Biaya sewa</dt><dd x-text="price && rupiah(price.rental_fee)"></dd></div>
                        <div class="flex justify-between py-1" x-show="price && price.charging_fee > 0">
                            <dt>Biaya isi listrik hingga penuh</dt><dd x-text="price && rupiah(price.charging_fee)"></dd>
                        </div>
                        <div class="flex justify-between border-t pt-2 mt-1 text-base font-semibold">
                            <dt>Total yang harus dibayar</dt><dd x-text="price && rupiah(price.total_cost)"></dd>
                        </div>
                    </dl>

                    <div>
                        <x-input-label for="payment_proof" value="Bukti pelunasan (JPG/PNG, maks. 2 MB)" />
                        <input id="payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png" required
                               class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2">
                        <x-input-error :messages="$errors->get('payment_proof')" class="mt-2" />
                    </div>
                </section>

                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('renter.rentals.index') }}" class="text-sm text-gray-600 underline">Batal</a>
                    <x-primary-button x-bind:disabled="!price">Simpan Sewa</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
