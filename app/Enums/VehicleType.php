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
