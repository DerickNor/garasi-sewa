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

## Struktur kode

Aplikasi memakai pola MVC dengan lapisan Service: controller hanya menerima request dan memilih tampilan,
seluruh aturan bisnis ada di Service.

```
Browser -> Route -> Middleware -> Controller -> FormRequest (validasi)
                                      |
                                      v
                                   Service -> Value Object (hasil hitung)
                                      |
                                      v
                                   Model -> MariaDB
Browser <------------ View (Blade) <--+
```

| Folder | Isi |
|---|---|
| `app/Http/Middleware` | `EnsureRole` (batasi halaman per role), `EnsureRenterApproved` (hanya penyewa yang sudah disetujui boleh menyewa) |
| `app/Http/Controllers` | `Admin/*` (garasi, verifikasi, perpanjangan, laporan), `Renter/*` (dashboard, sewa), `Auth/*` (daftar, masuk) |
| `app/Http/Requests` | Validasi form: daftar, buat garasi, sewa baru, perpanjangan |
| `app/Services` | Logika bisnis: `RentalPricingService` (rumus harga dan tanggal), `GarageService` (kode garasi, ketersediaan), `RentalService` (simpan sewa dan perpanjangan dalam transaksi), `ReportService` (laporan) |
| `app/ValueObjects` | `PriceBreakdown`, `ExtensionBreakdown`: hasil perhitungan biaya yang tidak bisa diubah |
| `app/Models` | `User`, `Garage`, `GarageSequence`, `Vehicle`, `Rental`, `RentalExtension` beserta relasinya |
| `app/Enums` | Tipe garasi, tipe kendaraan, tipe sewa, role, status, beserta aturan kecocokan garasi–kendaraan |
| `resources/views` | Tampilan Blade untuk admin, penyewa, dan autentikasi |
| `tests/Unit`, `tests/Feature` | Unit test (service dan enum) dan feature test (alur HTTP) |

Tempat aturan bisnis utama:

| Aturan | Lokasi |
|---|---|
| Sepeda/motor di garasi sepeda, mobil di garasi mobil, mobil listrik di garasi mobil atau mobil listrik | `GarageType::acceptsVehicle()` |
| Harga bulanan 25 × harian, durasi bulanan mengikuti jumlah hari bulan mulai | `RentalPricingService::calculateNewRental()` |
| Biaya isi listrik hingga penuh di garasi mobil listrik | `RentalPricingService::chargingFee()` |
| Kurang bayar perpanjangan (bulan penuh + sisa hari, selalu positif) | `RentalPricingService::calculateExtension()` |
| Kode garasi S001/C010/E001, maksimum 999 per tipe | `GarageService::createGarages()` |
| Garasi tersedia bila tidak ada sewa yang rentang tanggalnya bertabrakan | `Rental::scopeOverlapping()` |
| Status garasi `used` setelah disewa, kembali tersedia setelah sewa berakhir | `RentalService::createRental()`, `GarageService::releaseExpired()` |

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
php artisan storage:link
php artisan serve
```

Buka `http://localhost:8000`.

Status garasi yang masa sewanya habis dikembalikan otomatis setiap hari pukul 00:05 oleh penjadwal Laravel.
Untuk menjalankannya di lokal:

```bash
php artisan schedule:work
```

Perintah yang sama juga bisa dijalankan manual: `php artisan garages:release-expired`.

## Menjalankan test

```bash
php artisan test
```
