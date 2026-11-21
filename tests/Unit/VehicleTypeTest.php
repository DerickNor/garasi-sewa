<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use PHPUnit\Framework\TestCase;

/**
 * Unit test aturan tipe kendaraan (BR-01.1, REQ-RENT-02).
 */
class VehicleTypeTest extends TestCase
{
    /** TC-022: daftar tipe garasi yang boleh untuk tiap kendaraan. */
    public function test_allowed_garage_types(): void
    {
        $this->assertSame([GarageType::BICYCLE], VehicleType::BICYCLE->allowedGarageTypes());
        $this->assertSame([GarageType::BICYCLE], VehicleType::MOTORCYCLE->allowedGarageTypes());
        $this->assertSame([GarageType::CAR], VehicleType::CAR->allowedGarageTypes());
        $this->assertSame(
            [GarageType::CAR, GarageType::ELECTRIC_CAR],
            VehicleType::ELECTRIC_CAR->allowedGarageTypes(),
        );
    }

    /** TC-022: allowedGarageTypes konsisten dengan GarageType::acceptsVehicle. */
    public function test_allowed_garage_types_consistent_with_accepts_vehicle(): void
    {
        foreach (VehicleType::cases() as $vehicle) {
            foreach (GarageType::cases() as $garage) {
                $this->assertSame(
                    $garage->acceptsVehicle($vehicle),
                    in_array($garage, $vehicle->allowedGarageTypes(), true),
                    "{$vehicle->value} vs {$garage->value}",
                );
            }
        }
    }

    public function test_label_in_indonesian(): void
    {
        $this->assertSame('Sepeda', VehicleType::BICYCLE->label());
        $this->assertSame('Sepeda Motor', VehicleType::MOTORCYCLE->label());
        $this->assertSame('Mobil', VehicleType::CAR->label());
        $this->assertSame('Mobil Listrik', VehicleType::ELECTRIC_CAR->label());
    }

    /** Tipe garasi yang otomatis dipilih di form: mobil listrik ke garasi mobil listrik. */
    public function test_default_garage_type(): void
    {
        $this->assertSame(GarageType::BICYCLE, VehicleType::BICYCLE->defaultGarageType());
        $this->assertSame(GarageType::BICYCLE, VehicleType::MOTORCYCLE->defaultGarageType());
        $this->assertSame(GarageType::CAR, VehicleType::CAR->defaultGarageType());
        $this->assertSame(GarageType::ELECTRIC_CAR, VehicleType::ELECTRIC_CAR->defaultGarageType());

        foreach (VehicleType::cases() as $vehicle) {
            $this->assertContains($vehicle->defaultGarageType(), $vehicle->allowedGarageTypes());
        }
    }

    /** TC-023: nomor plat wajib kecuali sepeda. */
    public function test_requires_plate_except_bicycle(): void
    {
        $this->assertFalse(VehicleType::BICYCLE->requiresPlate());
        $this->assertTrue(VehicleType::MOTORCYCLE->requiresPlate());
        $this->assertTrue(VehicleType::CAR->requiresPlate());
        $this->assertTrue(VehicleType::ELECTRIC_CAR->requiresPlate());
    }

    /** TC-023: hanya mobil listrik yang isElectric. */
    public function test_only_electric_car_is_electric(): void
    {
        $this->assertFalse(VehicleType::BICYCLE->isElectric());
        $this->assertFalse(VehicleType::MOTORCYCLE->isElectric());
        $this->assertFalse(VehicleType::CAR->isElectric());
        $this->assertTrue(VehicleType::ELECTRIC_CAR->isElectric());
    }
}
