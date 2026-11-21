<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Perpanjang Sewa Garasi {{ $rental->garage->code }}</h2>
    </x-slot>

    <script>
        /**
         * Menampilkan rincian kurang bayar dari server setiap kali tanggal selesai baru diubah.
         */
        function extensionForm(config) {
            return {
                newEndDate: config.oldNewEndDate ?? '',
                result: null,
                message: '',

                init() {
                    if (this.newEndDate) this.refresh();
                },

                async refresh() {
                    this.result = null;
                    this.message = '';
                    if (!this.newEndDate) return;

                    const response = await fetch(config.previewUrl, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                        body: JSON.stringify({ new_end_date: this.newEndDate }),
                    });
                    const body = await response.json();

                    if (response.ok) {
                        this.result = body;
                    } else {
                        this.message = Object.values(body.errors ?? {}).flat()[0] ?? 'Kurang bayar belum dapat dihitung.';
                    }
                },

                rupiah(value) {
                    return 'Rp' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
                },

                date(value) {
                    return new Date(value + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                },
            };
        }
    </script>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Detail Sewa</h3>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Garasi</dt><dd class="font-medium">{{ $rental->garage->code }} ({{ $rental->garage->garage_type->label() }})</dd></div>
                    <div><dt class="text-gray-500">Penyewa</dt><dd class="font-medium">{{ $rental->user->name }} · {{ $rental->user->phone }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Kendaraan</dt>
                        <dd class="font-medium">{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}@if ($rental->vehicle->plate_number) · {{ $rental->vehicle->plate_number }}@endif</dd>
                    </div>
                    <div><dt class="text-gray-500">Harga harian (saat sewa dibuat)</dt><dd class="font-medium">@rupiah($rental->daily_price)</dd></div>
                    <div>
                        <dt class="text-gray-500">Masa sewa sekarang</dt>
                        <dd class="font-medium">{{ $rental->start_date->translatedFormat('j M Y') }} – {{ $rental->end_date->copy()->subDay()->translatedFormat('j M Y') }}</dd>
                    </div>
                    <div><dt class="text-gray-500">Tanggal selesai (dikembalikan)</dt><dd class="font-medium">{{ $rental->end_date->translatedFormat('j M Y') }}</dd></div>
                </dl>

                @if ($rental->extensions->isNotEmpty())
                    <div class="mt-4 border-t pt-4 text-sm">
                        <p class="text-gray-500 mb-2">Perpanjangan sebelumnya</p>
                        <ul class="space-y-1">
                            @foreach ($rental->extensions as $extension)
                                <li>{{ $extension->old_end_date->translatedFormat('j M Y') }} → {{ $extension->new_end_date->translatedFormat('j M Y') }} · @rupiah($extension->extra_cost)</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <form method="POST" action="{{ route('admin.extensions.store', $rental) }}" enctype="multipart/form-data"
                  x-data="extensionForm(@js([
                      'oldNewEndDate' => old('new_end_date'),
                      'previewUrl' => route('admin.extensions.preview', $rental),
                      'csrfToken' => csrf_token(),
                  ]))"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                @csrf

                <div>
                    <x-input-label for="new_end_date" value="Tanggal selesai baru (tanggal garasi dikembalikan)" />
                    <x-text-input id="new_end_date" name="new_end_date" type="date" class="mt-1 block w-full"
                                  min="{{ $rental->end_date->copy()->addDay()->toDateString() }}"
                                  x-model="newEndDate" @change="refresh()" required />
                    <x-input-error :messages="$errors->get('new_end_date')" class="mt-2" />
                </div>

                <p class="text-sm text-red-600" x-show="message" x-text="message"></p>

                <dl class="rounded-md bg-gray-50 p-4 text-sm" x-show="result">
                    <div class="flex justify-between py-1">
                        <dt>Masa sewa baru</dt>
                        <dd x-text="result && ('{{ $rental->start_date->translatedFormat('j M Y') }} – ' + date(result.last_day))"></dd>
                    </div>
                    <div class="flex justify-between py-1"><dt>Tambahan bulan penuh (25 × harian)</dt><dd x-text="result && result.months + ' bulan'"></dd></div>
                    <div class="flex justify-between py-1"><dt>Tambahan sisa hari</dt><dd x-text="result && result.days + ' hari'"></dd></div>
                    <div class="flex justify-between border-t pt-2 mt-1 text-base font-semibold">
                        <dt>Kurang bayar</dt><dd x-text="result && rupiah(result.cost)"></dd>
                    </div>
                </dl>

                <div>
                    <x-input-label for="payment_proof" value="Bukti pembayaran (JPG/PNG, maks. 2 MB)" />
                    <input id="payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png" required
                           class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2">
                    <x-input-error :messages="$errors->get('payment_proof')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('admin.extensions.index') }}" class="text-sm text-gray-600 underline">Batal</a>
                    <x-primary-button x-bind:disabled="!result">Simpan Perpanjangan</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
