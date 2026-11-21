<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use App\ValueObjects\ExtensionBreakdown;
use App\ValueObjects\PriceBreakdown;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Penyimpanan sewa baru (REQ-RENT-06, REQ-RENT-08, BR-06, BR-08, BR-10) dan perpanjangan sewa (REQ-EXT, BR-07).
 *
 * Data masukan berupa array hasil validasi form dengan kunci: vehicle_type, plate_number, brand, model,
 * battery_capacity_kwh, battery_start_kwh, rental_type, days, start_date, garage_id.
 */
class RentalService
{
    /** Tanggal mulai sewa paling lambat sekian hari dari hari ini (ASM-12). */
    public const MAX_ADVANCE_DAYS = 30;

    /** Disk dan folder penyimpanan bukti pembayaran. */
    public const PROOF_DISK = 'public';

    private const PROOF_DIRECTORY = 'proofs';

    /**
     * @param  RentalPricingService  $pricing  satu-satunya tempat rumus harga dan tanggal
     */
    public function __construct(private readonly RentalPricingService $pricing) {}

    /**
     * Hitung tanggal selesai dan biaya sewa tanpa menyimpan apa pun.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException bila kombinasi kendaraan, garasi, atau durasi tidak valid
     */
    public function previewRental(array $data): PriceBreakdown
    {
        return $this->calculate(Garage::findOrFail($data['garage_id']), $data);
    }

    /**
     * Simpan sewa baru dalam satu transaksi: kendaraan, sewa dengan salinan harga, dan status garasi `used`.
     *
     * Biaya selalu dihitung ulang di server. Garasi dikunci selama transaksi agar dua penyewa tidak
     * mendapat garasi yang sama. Bila gagal, file bukti yang sudah tersimpan ikut dihapus.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException bila sewa melanggar aturan bisnis (garasi tidak tersedia, plat ganda, dsb.)
     */
    public function createRental(User $renter, array $data, UploadedFile $proof): Rental
    {
        $proofPath = $proof->store(self::PROOF_DIRECTORY, self::PROOF_DISK);

        try {
            return DB::transaction(fn () => $this->storeRental($renter, $data, $proofPath));
        } catch (Throwable $e) {
            Storage::disk(self::PROOF_DISK)->delete($proofPath);

            throw $e;
        }
    }

    /**
     * Sewa yang belum berakhir pada $today (sedang berjalan atau akan datang), urut tanggal selesai (REQ-EXT-01).
     *
     * @return Collection<int, Rental>
     */
    public function activeRentals(CarbonInterface $today): Collection
    {
        return Rental::with(['garage', 'user', 'vehicle'])
            ->whereDate('end_date', '>', $today->toDateString())
            ->orderBy('end_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Hitung kurang bayar bila tanggal selesai $rental dimajukan ke $newEnd (REQ-EXT-03, BR-07).
     *
     * Memakai harga harian salinan di data sewa (BR-08), sehingga hasilnya selalu positif.
     *
     * @throws InvalidArgumentException bila sewa sudah berakhir, tanggal baru tidak maju, atau bertabrakan
     */
    public function previewExtension(Rental $rental, CarbonInterface $newEnd): ExtensionBreakdown
    {
        $newEnd = CarbonImmutable::instance($newEnd)->startOfDay();
        $oldEnd = CarbonImmutable::instance($rental->end_date)->startOfDay();

        if ($oldEnd->lte(today())) {
            throw new InvalidArgumentException('Sewa ini sudah berakhir sehingga tidak dapat diperpanjang.');
        }

        $extension = $this->pricing->calculateExtension($oldEnd, $newEnd, (float) $rental->daily_price);

        $garageBooked = Rental::where('garage_id', $rental->garage_id)
            ->whereKeyNot($rental->id)
            ->overlapping($oldEnd, $newEnd)
            ->exists();
        if ($garageBooked) {
            throw new InvalidArgumentException("Garasi {$rental->garage->code} sudah disewa orang lain pada tanggal tambahan tersebut.");
        }

        $vehicleBooked = Rental::where('vehicle_id', $rental->vehicle_id)
            ->whereKeyNot($rental->id)
            ->overlapping($oldEnd, $newEnd)
            ->exists();
        if ($vehicleBooked) {
            throw new InvalidArgumentException('Kendaraan ini sudah memiliki sewa lain pada tanggal tambahan tersebut.');
        }

        return $extension;
    }

    /**
     * Perpanjang sewa: simpan catatan perpanjangan dan ubah tanggal selesai dalam satu transaksi (REQ-EXT-04).
     *
     * @throws InvalidArgumentException bila perpanjangan melanggar aturan BR-07
     */
    public function extendRental(Rental $rental, CarbonInterface $newEnd, User $admin, UploadedFile $proof): RentalExtension
    {
        $proofPath = $proof->store(self::PROOF_DIRECTORY, self::PROOF_DISK);

        try {
            return DB::transaction(function () use ($rental, $newEnd, $admin, $proofPath) {
                Garage::whereKey($rental->garage_id)->lockForUpdate()->first();
                $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

                $breakdown = $this->previewExtension($rental, $newEnd);

                $extension = $rental->extensions()->create([
                    'processed_by' => $admin->id,
                    'old_end_date' => $rental->end_date,
                    'new_end_date' => $newEnd,
                    'extra_months' => $breakdown->months,
                    'extra_days' => $breakdown->days,
                    'extra_cost' => $breakdown->cost,
                    'payment_proof_path' => $proofPath,
                ]);

                $rental->update(['end_date' => $newEnd]);
                $rental->garage()->update(['status' => GarageStatus::USED]);

                return $extension;
            });
        } catch (Throwable $e) {
            Storage::disk(self::PROOF_DISK)->delete($proofPath);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeRental(User $renter, array $data, string $proofPath): Rental
    {
        $garage = Garage::whereKey($data['garage_id'])->lockForUpdate()->firstOrFail();
        $price = $this->calculate($garage, $data);
        $start = CarbonImmutable::parse($data['start_date'])->startOfDay();

        if (! $garage->isAvailableBetween($start, $price->endDate)) {
            throw new InvalidArgumentException("Garasi {$garage->code} sudah disewa pada tanggal tersebut. Silakan pilih garasi lain.");
        }

        $vehicleType = VehicleType::from($data['vehicle_type']);
        $plate = $vehicleType->requiresPlate() ? Vehicle::normalizePlate($data['plate_number'] ?? null) : null;

        if ($plate !== null && $this->plateIsBooked($plate, $start, $price->endDate)) {
            throw new InvalidArgumentException("Kendaraan {$plate} sudah memiliki sewa pada tanggal tersebut.");
        }

        $vehicle = $this->saveVehicle($renter, $vehicleType, $plate, $data);

        $rental = $renter->rentals()->create([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'rental_type' => RentalType::from($data['rental_type']),
            'duration_days' => $price->durationDays,
            'start_date' => $start,
            'end_date' => $price->endDate,
            'daily_price' => $garage->daily_price,
            'kwh_price' => $garage->kwh_price,
            'battery_start_kwh' => $vehicleType->isElectric() ? $data['battery_start_kwh'] : null,
            'rental_fee' => $price->rentalFee,
            'charging_fee' => $price->chargingFee,
            'total_cost' => $price->totalCost,
            'payment_proof_path' => $proofPath,
        ]);

        $garage->update(['status' => GarageStatus::USED]);

        return $rental;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calculate(Garage $garage, array $data): PriceBreakdown
    {
        $vehicleType = VehicleType::from($data['vehicle_type']);

        return $this->pricing->calculateNewRental(
            type: RentalType::from($data['rental_type']),
            days: isset($data['days']) ? (int) $data['days'] : null,
            start: CarbonImmutable::parse($data['start_date']),
            dailyPrice: (float) $garage->daily_price,
            garageType: $garage->garage_type,
            vehicleType: $vehicleType,
            batteryCapacityKwh: $vehicleType->isElectric() ? (float) $data['battery_capacity_kwh'] : null,
            batteryStartKwh: $vehicleType->isElectric() ? (float) $data['battery_start_kwh'] : null,
            kwhPrice: $garage->kwh_price !== null ? (float) $garage->kwh_price : null,
        );
    }

    /**
     * Apakah kendaraan berplat $plate (milik siapa pun) punya sewa yang bertabrakan (BR-10).
     */
    private function plateIsBooked(string $plate, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return Rental::overlapping($start, $end)
            ->whereHas('vehicle', fn ($vehicle) => $vehicle->where('plate_number', $plate))
            ->exists();
    }

    /**
     * Simpan data kendaraan. Kendaraan berplat milik penyewa yang sama dipakai ulang dan diperbarui.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveVehicle(User $renter, VehicleType $type, ?string $plate, array $data): Vehicle
    {
        $attributes = [
            'type' => $type,
            'brand' => $data['brand'],
            'model' => $data['model'],
            'battery_capacity_kwh' => $type->isElectric() ? $data['battery_capacity_kwh'] : null,
        ];

        if ($plate === null) {
            return $renter->vehicles()->create($attributes + ['plate_number' => null]);
        }

        return $renter->vehicles()->updateOrCreate(['plate_number' => $plate], $attributes);
    }
}
