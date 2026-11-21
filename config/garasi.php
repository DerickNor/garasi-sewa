<?php

/*
 * Konfigurasi khusus aplikasi Garasi Sewa.
 */
return [

    /*
     * Akun admin awal yang dibuat AdminSeeder (ASM-11). Ganti lewat .env untuk lingkungan nyata.
     */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@garasi.test'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

    /*
     * Password semua akun penyewa contoh yang dibuat DemoSeeder (hanya untuk demo lokal).
     */
    'demo' => [
        'password' => env('DEMO_PASSWORD', 'password'),
    ],

];
