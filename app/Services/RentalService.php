<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\ValueObjects\PriceBreakdown;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Penyimpanan sewa baru (REQ-RENT-06, REQ-RENT-08, BR-06, BR-08, BR-10).
 *
 * Data masukan berupa array hasil validasi form dengan kunci: vehicle_type, plate_number, brand, model,
 * battery_capacity_kwh, battery_start_kwh, rental_type, days, start_date, garage_id.
 */
class RentalService
{
    /** Disk dan folder penyimpanan bukti pembayaran. */
    public const PROOF_DISK = 'public';

    private const PROOF_DIRECTORY = 'proofs';

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
