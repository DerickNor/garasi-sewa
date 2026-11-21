<?php

/*
 * Pesan validasi berbahasa Indonesia untuk aturan yang dipakai aplikasi.
 * Aturan lain jatuh ke bahasa cadangan (en).
 */
return [
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'between' => [
        'numeric' => ':attribute harus antara :min dan :max.',
    ],
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang valid.',
    'digits' => ':attribute harus :digits digit angka.',
    'digits_between' => ':attribute harus :min sampai :max digit angka.',
    'email' => ':attribute harus alamat email yang valid.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa file.',
    'gt' => [
        'numeric' => ':attribute harus lebih besar dari :value.',
    ],
    'gte' => [
        'numeric' => ':attribute harus lebih besar dari atau sama dengan :value.',
    ],
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus bilangan bulat.',
    'lowercase' => ':attribute harus huruf kecil.',
    'lte' => [
        'numeric' => ':attribute harus lebih kecil dari atau sama dengan :value.',
    ],
    'max' => [
        'file' => ':attribute tidak boleh lebih dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimes' => ':attribute harus file bertipe: :values.',
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus mengandung minimal satu huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus mengandung minimal satu angka.',
        'symbols' => ':attribute harus mengandung minimal satu simbol.',
        'uncompromised' => ':attribute ini pernah bocor di internet. Gunakan :attribute lain.',
    ],
    'prohibited' => ':attribute tidak boleh diisi.',
    'prohibited_unless' => ':attribute tidak boleh diisi kecuali :other adalah :values.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi bila :other adalah :value.',
    'required_unless' => ':attribute wajib diisi kecuali :other adalah :values.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terdaftar.',
    'uploaded' => ':attribute gagal diunggah.',

    'attributes' => [
        'nik' => 'NIK',
        'name' => 'nama lengkap',
        'phone' => 'nomor telepon',
        'email' => 'email',
        'password' => 'password',
        'garage_type' => 'tipe garasi',
        'quantity' => 'jumlah garasi',
        'daily_price' => 'harga sewa harian',
        'kwh_price' => 'harga isi listrik per kWh',
        'vehicle_type' => 'tipe kendaraan',
        'plate_number' => 'nomor plat',
        'brand' => 'merek',
        'model' => 'tipe kendaraan',
        'battery_capacity_kwh' => 'kapasitas baterai',
        'battery_start_kwh' => 'kondisi baterai awal',
        'rental_type' => 'tipe sewa',
        'days' => 'jumlah hari',
        'garage_id' => 'garasi',
        'payment_proof' => 'bukti pembayaran',
        'new_end_date' => 'tanggal selesai baru',
    ],
];
