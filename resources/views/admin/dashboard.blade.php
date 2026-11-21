<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard Admin</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-6 sm:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">Pendaftar menunggu verifikasi</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $pendingRenters }}</p>
                <a href="{{ route('admin.renters.index') }}" class="mt-3 inline-block text-sm text-indigo-600 hover:underline">Lihat daftar pendaftar</a>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">Jumlah garasi</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $garageCount }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
