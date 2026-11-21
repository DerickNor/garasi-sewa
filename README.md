# Garasi Sewa — Aplikasi Penyewaan Garasi Kendaraan

Aplikasi web untuk uji kompetensi skema **Pemrogram** (FR.IA.04A).
Penyewa dapat mendaftar dan menyewa garasi sepeda, mobil, atau mobil listrik secara harian/bulanan;
admin membuat garasi berkelompok, memverifikasi pendaftar, memperpanjang sewa, dan melihat laporan.

## Teknologi

| Komponen | Versi / keterangan |
|---|---|
| PHP | 8.4 |
| Framework | Laravel 13 + Breeze (Blade, Tailwind CSS, Alpine.js) |
| Basis data | MariaDB 10.11 (XAMPP), akses via Eloquent |
| Testing | PHPUnit 12 (database `garasi_test`) |

## Dokumentasi

Diagram UML ada di [`docs/uml/`](docs/uml/): use case diagram, class diagram, dan ERD.

## Menjalankan secara lokal

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Buat database `garasi` dan `garasi_test` (collation `utf8mb4_unicode_ci`) di MariaDB/MySQL, lalu:

```bash
php artisan migrate --seed
php artisan serve
```

Buka `http://localhost:8000`.

## Menjalankan test

```bash
php artisan test
```
