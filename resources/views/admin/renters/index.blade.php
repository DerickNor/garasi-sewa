<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verifikasi Pendaftar Baru</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                @if ($renters->isEmpty())
                    <p class="p-6 text-sm text-gray-500">Tidak ada pendaftar yang menunggu verifikasi.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">NIK</th>
                                <th class="px-4 py-3 font-medium">Nama</th>
                                <th class="px-4 py-3 font-medium">Telepon</th>
                                <th class="px-4 py-3 font-medium">Email</th>
                                <th class="px-4 py-3 font-medium">Tanggal Daftar</th>
                                <th class="px-4 py-3 font-medium text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($renters as $renter)
                                <tr>
                                    <td class="px-4 py-3 font-mono">{{ $renter->nik }}</td>
                                    <td class="px-4 py-3">{{ $renter->name }}</td>
                                    <td class="px-4 py-3">{{ $renter->phone }}</td>
                                    <td class="px-4 py-3">{{ $renter->email }}</td>
                                    <td class="px-4 py-3">{{ $renter->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.renters.approve', $renter) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">Terima</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.renters.reject', $renter) }}"
                                                  onsubmit="return confirm('Tolak pendaftaran {{ $renter->name }}?')">
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
