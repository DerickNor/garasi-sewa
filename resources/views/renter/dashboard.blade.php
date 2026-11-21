@php use App\Enums\VerificationStatus; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard Penyewa</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @switch($user->verification_status)
                @case(VerificationStatus::PENDING)
                    <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4 text-sm text-yellow-800">
                        Akun Anda <strong>menunggu verifikasi admin</strong>. Fitur sewa garasi dapat dipakai setelah pendaftaran disetujui.
                    </div>
                    @break
                @case(VerificationStatus::REJECTED)
                    <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                        Pendaftaran Anda <strong>ditolak</strong> oleh admin, sehingga Anda tidak dapat menyewa garasi.
                    </div>
                    @break
                @default
                    <div class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-800 flex flex-wrap items-center justify-between gap-3">
                        <span>Akun Anda sudah <strong>disetujui</strong>. Anda dapat menyewa garasi.</span>
                        <span class="flex gap-3">
                            <a href="{{ route('renter.rentals.create') }}" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Sewa Garasi Baru</a>
                            <a href="{{ route('renter.rentals.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">Riwayat Sewa</a>
                        </span>
                    </div>
            @endswitch

            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-700">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-gray-500">Nama</dt><dd class="font-medium">{{ $user->name }}</dd></div>
                    <div><dt class="text-gray-500">NIK</dt><dd class="font-medium">{{ $user->nik }}</dd></div>
                    <div><dt class="text-gray-500">Telepon</dt><dd class="font-medium">{{ $user->phone }}</dd></div>
                    <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $user->email }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
