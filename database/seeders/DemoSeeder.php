<?php

namespace Database\Seeders;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Enums\VerificationStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Services\GarageService;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data contoh untuk demo presentasi. Tanggal dibuat relatif terhadap hari ini sehingga selalu ada
 * sewa yang sudah selesai, sedang berjalan, akan datang, dan satu sewa yang sudah diperpanjang.
 *
 * Jalankan: php artisan migrate:fresh --seed
 */
class DemoSeeder extends Seeder
{
    /** Gambar PNG 1×1 piksel sebagai bukti pembayaran contoh. */
    private const PLACEHOLDER_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public function __construct(
        private readonly GarageService $garages,
        private readonly RentalPricingService $pricing,
    ) {}

    /**
     * Buat akun penyewa, garasi, dan riwayat sewa contoh.
     */
    public function run(): void
    {
        $today = CarbonImmutable::today();
        $admin = User::where('role', UserRole::ADMIN)->firstOrFail();

        $budi = $this->renter('Budi Santoso', 'budi@garasi.test', '3578010101900001', '081234500001', VerificationStatus::APPROVED);
        $sari = $this->renter('Sari Wulandari', 'sari@garasi.test', '3578010101900002', '081234500002', VerificationStatus::APPROVED);
        $this->renter('Dewi Lestari', 'dewi@garasi.test', '3578010101900003', '081234500003', VerificationStatus::PENDING);
        $this->renter('Rudi Hartono', 'rudi@garasi.test', '3578010101900004', '081234500004', VerificationStatus::REJECTED);

        // Garasi dibuat lewat GarageService agar kodenya sama seperti saat admin membuat garasi.
        $bicycle = $this->garages->createGarages(GarageType::BICYCLE, 5, 5000, null);
        $car = $this->garages->createGarages(GarageType::CAR, 5, 100000, null);
        $electric = $this->garages->createGarages(GarageType::ELECTRIC_CAR, 3, 150000, 2500);

        $avanza = ['type' => VehicleType::CAR, 'plate_number' => 'L 1234 BS', 'brand' => 'Toyota', 'model' => 'Avanza'];
        $vario = ['type' => VehicleType::MOTORCYCLE, 'plate_number' => 'L 5678 BS', 'brand' => 'Honda', 'model' => 'Vario'];
        $ioniq = ['type' => VehicleType::ELECTRIC_CAR, 'plate_number' => 'L 9012 BS', 'brand' => 'Hyundai', 'model' => 'Ioniq 5', 'battery_capacity_kwh' => 60];
        $jazz = ['type' => VehicleType::CAR, 'plate_number' => 'W 2468 SW', 'brand' => 'Honda', 'model' => 'Jazz'];
        $polygon = ['type' => VehicleType::BICYCLE, 'plate_number' => null, 'brand' => 'Polygon', 'model' => 'Xtrada'];

        // Sewa yang sudah selesai di bulan-bulan sebelumnya (mengisi laporan per bulan).
        $this->rental($budi, $car[0], $avanza, RentalType::DAILY, 4, $today->subMonthsNoOverflow(5)->startOfMonth()->addDays(4));
        $this->rental($sari, $bicycle[0], $polygon, RentalType::MONTHLY, null, $today->subMonthsNoOverflow(4)->startOfMonth()->addDays(9));
        $this->rental($budi, $electric[0], $ioniq, RentalType::DAILY, 2, $today->subMonthsNoOverflow(3)->startOfMonth()->addDays(14), 30);
        $this->rental($sari, $car[1], $jazz, RentalType::DAILY, 6, $today->subMonthsNoOverflow(2)->startOfMonth()->addDays(2));
        $this->rental($budi, $bicycle[1], $vario, RentalType::DAILY, 10, $today->subMonthsNoOverflow(1)->startOfMonth()->addDays(6));

        // Sewa yang sedang berjalan.
        $this->rental($budi, $electric[1], $ioniq, RentalType::DAILY, 3, $today->subDay(), 22.5);
        $this->rental($budi, $bicycle[2], $vario, RentalType::MONTHLY, null, $today->subDays(10));
        $sariCar = $this->rental($sari, $car[2], $jazz, RentalType::DAILY, 5, $today);

        // Sewa yang akan datang.
        $this->rental($sari, $car[3], $avanza + ['plate_number' => 'W 1357 SW'], RentalType::DAILY, 2, $today->addDays(5));

        // Satu sewa yang sudah diperpanjang admin 2 hari.
        $this->extend($sariCar, $admin, 2);

        $this->syncGarageStatuses($today);
    }

    private function renter(string $name, string $email, string $nik, string $phone, VerificationStatus $status): User
    {
        $user = new User(['nik' => $nik, 'name' => $name, 'phone' => $phone, 'email' => $email, 'password' => config('garasi.demo.password')]);
        $user->role = UserRole::PENYEWA;
        $user->verification_status = $status;
        $user->verified_at = $status === VerificationStatus::APPROVED ? now() : null;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $vehicle
     */
    private function rental(
        User $renter,
        Garage $garage,
        array $vehicle,
        RentalType $type,
        ?int $days,
        CarbonImmutable $start,
        ?float $batteryStart = null,
    ): Rental {
        $vehicleType = $vehicle['type'];
        $price = $this->pricing->calculateNewRental(
            type: $type,
            days: $days,
            start: $start,
            dailyPrice: (float) $garage->daily_price,
            garageType: $garage->garage_type,
            vehicleType: $vehicleType,
            batteryCapacityKwh: $vehicle['battery_capacity_kwh'] ?? null,
            batteryStartKwh: $batteryStart,
            kwhPrice: $garage->kwh_price !== null ? (float) $garage->kwh_price : null,
        );

        $vehicleModel = $vehicle['plate_number'] === null
            ? $renter->vehicles()->create($vehicle)
            : $renter->vehicles()->firstOrCreate(['plate_number' => $vehicle['plate_number']], $vehicle);

        return $renter->rentals()->create([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicleModel->id,
            'rental_type' => $type,
            'duration_days' => $price->durationDays,
            'start_date' => $start,
            'end_date' => $price->endDate,
            'daily_price' => $garage->daily_price,
            'kwh_price' => $garage->kwh_price,
            'battery_start_kwh' => $vehicleType === VehicleType::ELECTRIC_CAR ? $batteryStart : null,
            'rental_fee' => $price->rentalFee,
            'charging_fee' => $price->chargingFee,
            'total_cost' => $price->totalCost,
            'payment_proof_path' => $this->proof(),
        ]);
    }

    private function extend(Rental $rental, User $admin, int $extraDays): void
    {
        $newEnd = $rental->end_date->copy()->addDays($extraDays);
        $breakdown = $this->pricing->calculateExtension($rental->end_date, $newEnd, (float) $rental->daily_price);

        $rental->extensions()->create([
            'processed_by' => $admin->id,
            'old_end_date' => $rental->end_date,
            'new_end_date' => $newEnd,
            'extra_months' => $breakdown->months,
            'extra_days' => $breakdown->days,
            'extra_cost' => $breakdown->cost,
            'payment_proof_path' => $this->proof(),
        ]);

        $rental->update(['end_date' => $newEnd]);
    }

    /**
     * Garasi dengan sewa berjalan atau akan datang berstatus used, lainnya tersedia.
     */
    private function syncGarageStatuses(CarbonImmutable $today): void
    {
        Garage::whereHas('rentals', fn ($rentals) => $rentals->whereDate('end_date', '>', $today->toDateString()))
            ->update(['status' => GarageStatus::USED]);

        $this->garages->releaseExpired($today);
    }

    private function proof(): string
    {
        $path = 'proofs/demo-'.uniqid().'.png';
        Storage::disk(RentalService::PROOF_DISK)->put($path, base64_decode(self::PLACEHOLDER_PNG));

        return $path;
    }
}
