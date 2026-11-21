<?php

namespace App\Enums;

/**
 * Tipe garasi beserta aturan kompatibilitas kendaraan (BR-01) dan prefix kode (BR-05).
 */
enum GarageType: string
{
    case BICYCLE = 'bicycle';
    case CAR = 'car';
    case ELECTRIC_CAR = 'electric_car';

    /**
     * Huruf awalan kode garasi, mis. S untuk S001.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::BICYCLE => 'S',
            self::CAR => 'C',
            self::ELECTRIC_CAR => 'E',
        };
    }

    /**
     * Apakah kendaraan bertipe $type boleh ditempatkan di garasi ini (BR-01).
     *
     * Satu-satunya tempat aturan kompatibilitas didefinisikan.
     */
    public function acceptsVehicle(VehicleType $type): bool
    {
        return match ($this) {
            self::BICYCLE => in_array($type, [VehicleType::BICYCLE, VehicleType::MOTORCYCLE], true),
            self::CAR => in_array($type, [VehicleType::CAR, VehicleType::ELECTRIC_CAR], true),
            self::ELECTRIC_CAR => $type === VehicleType::ELECTRIC_CAR,
        };
    }
}
