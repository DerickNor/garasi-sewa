<?php

namespace App\Enums;

/**
 * Tipe kendaraan penyewa (REQ-RENT-02).
 */
enum VehicleType: string
{
    case BICYCLE = 'bicycle';
    case MOTORCYCLE = 'motorcycle';
    case CAR = 'car';
    case ELECTRIC_CAR = 'electric_car';

    /**
     * Nama tipe kendaraan untuk ditampilkan.
     */
    public function label(): string
    {
        return match ($this) {
            self::BICYCLE => 'Sepeda',
            self::MOTORCYCLE => 'Sepeda Motor',
            self::CAR => 'Mobil',
            self::ELECTRIC_CAR => 'Mobil Listrik',
        };
    }

    /**
     * Nomor plat wajib untuk semua kendaraan kecuali sepeda.
     */
    public function requiresPlate(): bool
    {
        return $this !== self::BICYCLE;
    }

    /**
     * Hanya mobil listrik yang memiliki kapasitas dan kondisi baterai (BR-04).
     */
    public function isElectric(): bool
    {
        return $this === self::ELECTRIC_CAR;
    }

    /**
     * Tipe garasi yang otomatis dipilih di form sewa. Mobil listrik diarahkan ke garasi mobil listrik
     * (agar baterai diisi), tetapi penyewa tetap boleh menggantinya ke garasi mobil.
     */
    public function defaultGarageType(): GarageType
    {
        return match ($this) {
            self::BICYCLE, self::MOTORCYCLE => GarageType::BICYCLE,
            self::CAR => GarageType::CAR,
            self::ELECTRIC_CAR => GarageType::ELECTRIC_CAR,
        };
    }

    /**
     * Tipe garasi yang boleh dipilih untuk kendaraan ini, diturunkan dari GarageType::acceptsVehicle.
     *
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return array_values(array_filter(
            GarageType::cases(),
            fn (GarageType $garage): bool => $garage->acceptsVehicle($this),
        ));
    }
}
