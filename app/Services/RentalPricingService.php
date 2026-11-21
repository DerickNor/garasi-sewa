<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\ValueObjects\ExtensionBreakdown;
use App\ValueObjects\PriceBreakdown;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Seluruh aturan harga dan tanggal sewa (BR-02, BR-03, BR-04, BR-07).
 *
 * Murni perhitungan: tidak mengakses database, request, atau session sehingga mudah diuji.
 */
class RentalPricingService
{
    /** Harga sewa bulanan = 25 × harga harian (BR-02.2). */
    public const MONTHLY_MULTIPLIER = 25;

    /** Pembulatan nilai uang ke sen. */
    private const MONEY_PRECISION = 2;

    /**
     * Jumlah hari pada bulan dan tahun tanggal $date; Februari mengikuti tahun kabisat (BR-03).
     */
    public function daysInMonth(CarbonInterface $date): int
    {
        return $date->daysInMonth;
    }

    /**
     * Maksimum lama sewa harian bila mulai pada $start: hari dalam bulan − 1 (BR-03.1).
     */
    public function maxDailyDays(CarbonInterface $start): int
    {
        return $this->daysInMonth($start) - 1;
    }

    /**
     * Harga sewa satu bulan (BR-02.2).
     */
    public function monthlyPrice(float $dailyPrice): float
    {
        return $this->money(self::MONTHLY_MULTIPLIER * $dailyPrice);
    }

    /**
     * Lama sewa dalam hari: jumlah hari input untuk harian, jumlah hari bulan tanggal mulai untuk bulanan (BR-03).
     *
     * @param  int|null  $days  jumlah hari, wajib untuk sewa harian; diabaikan untuk bulanan
     *
     * @throws InvalidArgumentException bila jumlah hari harian di luar 1..maxDailyDays
     */
    public function durationDays(RentalType $type, ?int $days, CarbonInterface $start): int
    {
        if ($type === RentalType::MONTHLY) {
            return $this->daysInMonth($start);
        }

        $maxDays = $this->maxDailyDays($start);

        if ($days === null || $days < 1 || $days > $maxDays) {
            throw new InvalidArgumentException("Jumlah hari sewa harian harus 1 sampai {$maxDays}.");
        }

        return $days;
    }

    /**
     * Hitung durasi, tanggal selesai, dan biaya sewa baru.
     *
     * @param  int|null  $days  jumlah hari, wajib untuk sewa harian; diabaikan untuk bulanan
     * @param  float|null  $batteryCapacityKwh  kapasitas baterai, wajib untuk mobil listrik
     * @param  float|null  $batteryStartKwh  isi baterai saat masuk, wajib untuk mobil listrik
     * @param  float|null  $kwhPrice  harga isi listrik per kWh, wajib untuk garasi mobil listrik
     *
     * @throws InvalidArgumentException bila input melanggar BR-01, BR-03, atau BR-04
     */
    public function calculateNewRental(
        RentalType $type,
        ?int $days,
        CarbonInterface $start,
        float $dailyPrice,
        GarageType $garageType,
        VehicleType $vehicleType,
        ?float $batteryCapacityKwh = null,
        ?float $batteryStartKwh = null,
        ?float $kwhPrice = null,
    ): PriceBreakdown {
        // 1. Kendaraan harus cocok dengan tipe garasi (BR-01), mis. mobil tidak boleh di garasi sepeda.
        if (! $garageType->acceptsVehicle($vehicleType)) {
            throw new InvalidArgumentException('Tipe garasi tidak sesuai dengan tipe kendaraan.');
        }

        // 2. Lama sewa: harian = jumlah hari input; bulanan = jumlah hari bulan tanggal mulai (28/29/30/31).
        $start = CarbonImmutable::instance($start)->startOfDay();
        $duration = $this->durationDays($type, $days, $start);

        // 3. Biaya sewa: harian = hari x harga harian; bulanan = 25 x harga harian (tidak bergantung panjang bulan).
        $rentalFee = $type === RentalType::MONTHLY
            ? $this->monthlyPrice($dailyPrice)
            : $this->money($duration * $dailyPrice);

        // 4. Biaya isi listrik hanya untuk mobil listrik, dan hanya ditagih bila parkir di garasi mobil listrik.
        $chargingFee = $vehicleType->isElectric()
            ? $this->chargingFee($garageType, $batteryCapacityKwh, $batteryStartKwh, $kwhPrice)
            : 0.0;

        // 5. Tanggal selesai = mulai + durasi (eksklusif): sewa 3 hari mulai 2 Jan selesai 5 Jan,
        //    garasi dipakai 2, 3, 4 Jan dan sudah bisa disewa orang lain pada 5 Jan (ASM-01).
        return new PriceBreakdown(
            durationDays: $duration,
            endDate: $start->addDays($duration),
            rentalFee: $rentalFee,
            chargingFee: $chargingFee,
            totalCost: $this->money($rentalFee + $chargingFee),
        );
    }

    /**
     * Hitung kurang bayar perpanjangan dari $oldEnd ke $newEnd (BR-07, ASM-04, ASM-05).
     *
     * Selisih tanggal dipecah menjadi bulan penuh (panjang bulan mengikuti kalender mulai dari
     * $oldEnd, dihargai 25 × harian) dan sisa hari (dihargai per hari).
     *
     * @throws InvalidArgumentException bila $newEnd tidak lebih besar dari $oldEnd
     */
    public function calculateExtension(CarbonInterface $oldEnd, CarbonInterface $newEnd, float $dailyPrice): ExtensionBreakdown
    {
        $cursor = CarbonImmutable::instance($oldEnd)->startOfDay();
        $target = CarbonImmutable::instance($newEnd)->startOfDay();

        if ($target->lte($cursor)) {
            throw new InvalidArgumentException('Tanggal selesai baru harus setelah tanggal selesai lama.');
        }

        // Maju satu bulan penuh selama belum melewati tanggal selesai baru. Panjang "satu bulan" mengikuti
        // kalender dari posisi saat ini, sama seperti sewa bulanan baru.
        // Contoh 21 Des 2026 -> 24 Jan 2027:
        //   21 Des + 31 hari (Desember) = 21 Jan <= 24 Jan  -> 1 bulan, posisi pindah ke 21 Jan
        //   21 Jan + 31 hari (Januari)  = 21 Feb >  24 Jan  -> berhenti
        //   sisa 21 Jan -> 24 Jan = 3 hari; biaya = 1 x (25 x harian) + 3 x harian
        $months = 0;

        while ($cursor->addDays($this->daysInMonth($cursor))->lte($target)) {
            $cursor = $cursor->addDays($this->daysInMonth($cursor));
            $months++;
        }

        // Sisa hari setelah bulan penuh terakhir dihargai per hari.
        $days = (int) $cursor->diffInDays($target);
        $cost = $this->money($months * $this->monthlyPrice($dailyPrice) + $days * $dailyPrice);

        return new ExtensionBreakdown($months, $days, $cost);
    }

    /**
     * Biaya isi listrik hingga penuh untuk mobil listrik (BR-04). Hanya dikenakan di garasi E.
     *
     * @throws InvalidArgumentException bila data baterai tidak valid atau harga kWh garasi E kosong
     */
    private function chargingFee(GarageType $garageType, ?float $capacity, ?float $battery, ?float $kwhPrice): float
    {
        if ($capacity === null || $capacity <= 0) {
            throw new InvalidArgumentException('Kapasitas baterai mobil listrik harus lebih dari 0.');
        }

        if ($battery === null || $battery < 0 || $battery > $capacity) {
            throw new InvalidArgumentException('Kondisi baterai awal harus antara 0 dan kapasitas baterai.');
        }

        // Mobil listrik di garasi mobil biasa (C): baterai tetap dicatat, tetapi tidak diisi sehingga gratis.
        if ($garageType !== GarageType::ELECTRIC_CAR) {
            return 0.0;
        }

        if ($kwhPrice === null) {
            throw new InvalidArgumentException('Harga isi listrik per kWh garasi mobil listrik belum diisi.');
        }

        // Diisi hingga penuh: kekurangan kWh x harga per kWh. Contoh (60 - 22,5) x Rp2.500 = Rp93.750.
        return $this->money(($capacity - $battery) * $kwhPrice);
    }

    /**
     * Bulatkan nilai uang ke 2 angka desimal (sen) agar tidak ada sisa pembulatan float.
     */
    private function money(float $amount): float
    {
        return round($amount, self::MONEY_PRECISION);
    }
}
