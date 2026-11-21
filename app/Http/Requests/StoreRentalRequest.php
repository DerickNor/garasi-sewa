<?php

namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Vehicle;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi form sewa baru (REQ-RENT-01..07, design.md bagian 10).
 *
 * Ketersediaan garasi dan plat ganda diperiksa ulang oleh RentalService di dalam transaksi.
 */
class StoreRentalRequest extends FormRequest
{
    /** Format nomor plat setelah dirapikan, mis. "L 1234 AB" atau "B 1 A". */
    private const PLATE_PATTERN = '/^[A-Z]{1,2} \d{1,4}( [A-Z]{1,3})?$/';

    /** Ukuran maksimum bukti pembayaran dalam kilobyte (ASM-08). */
    private const MAX_PROOF_KB = 2048;

    /** Batas atas kapasitas baterai (kWh) yang masuk akal. */
    private const MAX_BATTERY_KWH = 999;

    /**
     * Hak akses sudah dibatasi middleware role:penyewa dan renter.approved pada rute.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rapikan nomor plat sebelum divalidasi, mis. "l1234ab" menjadi "L 1234 AB".
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('plate_number')) {
            $this->merge(['plate_number' => Vehicle::normalizePlate($this->input('plate_number'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = today();

        return [
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'plate_number' => ['exclude_if:vehicle_type,'.VehicleType::BICYCLE->value, 'required', 'string', 'max:15', 'regex:'.self::PLATE_PATTERN],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'battery_capacity_kwh' => ['exclude_unless:vehicle_type,'.VehicleType::ELECTRIC_CAR->value, 'required', 'numeric', 'gt:0', 'max:'.self::MAX_BATTERY_KWH],
            'battery_start_kwh' => ['exclude_unless:vehicle_type,'.VehicleType::ELECTRIC_CAR->value, 'required', 'numeric', 'min:0', 'lte:battery_capacity_kwh'],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'days' => ['exclude_unless:rental_type,'.RentalType::DAILY->value, 'required', 'integer', 'min:1'],
            'start_date' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$today->copy()->addDays(RentalService::MAX_ADVANCE_DAYS)->toDateString(),
            ],
            'garage_type' => ['required', Rule::enum(GarageType::class)],
            'garage_id' => ['required', 'integer', Rule::exists(Garage::class, 'id')],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PROOF_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plate_number.regex' => 'Format nomor plat tidak valid, contoh: L 1234 AB.',
            'battery_start_kwh.lte' => 'Kondisi baterai awal tidak boleh melebihi kapasitas baterai.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'start_date.before_or_equal' => 'Tanggal mulai paling lambat '.RentalService::MAX_ADVANCE_DAYS.' hari dari hari ini.',
            'payment_proof.max' => 'Bukti pembayaran maksimal 2 MB.',
        ];
    }

    /**
     * Aturan yang bergantung pada beberapa isian: kecocokan garasi-kendaraan (BR-01) dan batas hari (BR-03.1).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errors = $validator->errors();

                if (! $errors->hasAny(['vehicle_type', 'garage_type'])) {
                    $vehicle = VehicleType::from($this->input('vehicle_type'));
                    $garage = GarageType::from($this->input('garage_type'));

                    if (! $garage->acceptsVehicle($vehicle)) {
                        $errors->add('garage_type', "{$vehicle->label()} tidak dapat ditempatkan di garasi {$garage->label()}.");
                    } elseif (! $errors->has('garage_id')
                        && Garage::find($this->input('garage_id'))?->garage_type !== $garage) {
                        $errors->add('garage_id', 'Garasi yang dipilih bukan garasi '.$garage->label().'.');
                    }
                }

                if ($this->input('rental_type') === RentalType::DAILY->value && ! $errors->hasAny(['days', 'start_date'])) {
                    $maxDays = app(RentalPricingService::class)->maxDailyDays(CarbonImmutable::parse($this->input('start_date')));

                    if ((int) $this->input('days') > $maxDays) {
                        $errors->add('days', "Jumlah hari sewa harian maksimal {$maxDays} hari untuk bulan tanggal mulai.");
                    }
                }
            },
        ];
    }
}
