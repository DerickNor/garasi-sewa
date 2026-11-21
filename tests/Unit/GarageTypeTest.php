<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit test aturan tipe garasi (BR-01, BR-05.1).
 */
class GarageTypeTest extends TestCase
{
    /**
     * Matriks kompatibilitas BR-01: [tipe garasi, tipe kendaraan, diterima?].
     *
     * @return array<string, array{GarageType, VehicleType, bool}>
     */
    public static function compatibilityMatrix(): array
    {
        return [
            'S menerima sepeda' => [GarageType::BICYCLE, VehicleType::BICYCLE, true],
            'S menerima sepeda motor' => [GarageType::BICYCLE, VehicleType::MOTORCYCLE, true],
            'S menolak mobil' => [GarageType::BICYCLE, VehicleType::CAR, false],
            'S menolak mobil listrik' => [GarageType::BICYCLE, VehicleType::ELECTRIC_CAR, false],
            'C menolak sepeda' => [GarageType::CAR, VehicleType::BICYCLE, false],
            'C menolak sepeda motor' => [GarageType::CAR, VehicleType::MOTORCYCLE, false],
            'C menerima mobil' => [GarageType::CAR, VehicleType::CAR, true],
            'C menerima mobil listrik' => [GarageType::CAR, VehicleType::ELECTRIC_CAR, true],
            'E menolak sepeda' => [GarageType::ELECTRIC_CAR, VehicleType::BICYCLE, false],
            'E menolak sepeda motor' => [GarageType::ELECTRIC_CAR, VehicleType::MOTORCYCLE, false],
            'E menolak mobil' => [GarageType::ELECTRIC_CAR, VehicleType::CAR, false],
            'E menerima mobil listrik' => [GarageType::ELECTRIC_CAR, VehicleType::ELECTRIC_CAR, true],
        ];
    }

    /** TC-021: GarageType::acceptsVehicle mengikuti tabel BR-01. */
    #[DataProvider('compatibilityMatrix')]
    public function test_accepts_vehicle_follows_br01(GarageType $garage, VehicleType $vehicle, bool $expected): void
    {
        $this->assertSame($expected, $garage->acceptsVehicle($vehicle));
    }

    /** TC-024: prefix kode garasi S, C, E. */
    public function test_prefix_per_garage_type(): void
    {
        $this->assertSame('S', GarageType::BICYCLE->prefix());
        $this->assertSame('C', GarageType::CAR->prefix());
        $this->assertSame('E', GarageType::ELECTRIC_CAR->prefix());
    }
}
