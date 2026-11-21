<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class RentalPricingService
{
    public const MONTHLY_DAY_MULTIPLIER = 25;

    /**
     * Vehicle-to-garage compatibility matrix.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_GARAGE_TYPES = [
        Rental::VEHICLE_SEPEDA => [Garage::TYPE_SEPEDA],
        Rental::VEHICLE_SEPEDA_MOTOR => [Garage::TYPE_SEPEDA],
        Rental::VEHICLE_MOBIL => [Garage::TYPE_MOBIL],
        Rental::VEHICLE_MOBIL_LISTRIK => [Garage::TYPE_MOBIL, Garage::TYPE_MOBIL_LISTRIK],
    ];

    /**
     * Return allowed garage types for the given vehicle type.
     *
     * @return list<string>
     */
    public function allowedGarageTypes(string $vehicleType): array
    {
        return self::ALLOWED_GARAGE_TYPES[$vehicleType] ?? [];
    }

    /**
     * Determine whether the vehicle type is allowed in the given garage type.
     */
    public function isCompatible(string $vehicleType, string $garageType): bool
    {
        return in_array($garageType, $this->allowedGarageTypes($vehicleType), true);
    }

    /**
     * Determine whether a license plate is required for the given vehicle type.
     */
    public function requiresLicensePlate(string $vehicleType): bool
    {
        return $vehicleType !== Rental::VEHICLE_SEPEDA;
    }

    /**
     * Determine whether battery capacity (kWh) is required for the given vehicle type.
     */
    public function requiresBatteryCapacity(string $vehicleType): bool
    {
        return $vehicleType === Rental::VEHICLE_MOBIL_LISTRIK;
    }

    /**
     * Maximum allowed days for a harian rental starting on the given date.
     */
    public function maxDailyDays(Carbon $startDate): int
    {
        return $startDate->daysInMonth - 1;
    }

    /**
     * Resolve and validate the initial duration in days.
     *
     * @throws ValidationException
     */
    public function calculateInitialDurationDays(
        string $rentalType,
        Carbon $startDate,
        ?int $jumlahHari = null
    ): int {
        if ($rentalType === Rental::TYPE_HARIAN) {
            $maxDays = $this->maxDailyDays($startDate);

            if ($jumlahHari === null || $jumlahHari < 1 || $jumlahHari > $maxDays) {
                throw ValidationException::withMessages([
                    'jumlah_hari' => "Jumlah hari sewa harian harus antara 1 sampai {$maxDays} hari untuk bulan ini.",
                ]);
            }

            return $jumlahHari;
        }

        if ($rentalType === Rental::TYPE_BULANAN) {
            return $startDate->daysInMonth;
        }

        throw ValidationException::withMessages([
            'rental_type' => 'Tipe sewa tidak valid.',
        ]);
    }

    /**
     * Compute the inclusive end date (start_date + duration_days - 1).
     */
    public function calculateInitialEndDate(Carbon $startDate, int $durationDays): Carbon
    {
        return $startDate->copy()->startOfDay()->addDays($durationDays - 1);
    }

    /**
     * Calculate the monthly base rate (25 * daily_rate).
     */
    public function calculateMonthlyBaseRate(Garage $garage): string
    {
        return $this->multiplyMoney(self::MONTHLY_DAY_MULTIPLIER, $garage->daily_rate);
    }

    /**
     * Calculate the one-time EV charging fee (0% -> 100%).
     * Applied ONLY when garage.type === 'mobil_listrik'; otherwise strictly 0.00.
     *
     * @throws ValidationException
     */
    public function calculateEvChargingFee(
        Garage $garage,
        string $vehicleType,
        float|int|string|null $batteryCapacityKwh
    ): string {
        if ($garage->type !== Garage::TYPE_MOBIL_LISTRIK) {
            return '0.00';
        }

        if ($vehicleType !== Rental::VEHICLE_MOBIL_LISTRIK) {
            throw ValidationException::withMessages([
                'garage_id' => 'Garasi mobil listrik hanya dapat digunakan untuk kendaraan mobil listrik.',
            ]);
        }

        if ($batteryCapacityKwh === null || (float) $batteryCapacityKwh <= 0) {
            throw ValidationException::withMessages([
                'battery_capacity_kwh' => 'Kapasitas baterai (kWh) wajib diisi dan lebih besar dari 0 untuk mobil listrik.',
            ]);
        }

        if ($garage->kwh_rate === null || (float) $garage->kwh_rate <= 0) {
            throw ValidationException::withMessages([
                'garage_id' => 'Tarif per kWh tidak tersedia pada garasi mobil listrik ini.',
            ]);
        }

        return $this->multiplyMoney($batteryCapacityKwh, $garage->kwh_rate);
    }

    /**
     * Validate compatibility and compute full initial rental quote.
     *
     * @return array{
     *     start_date: Carbon,
     *     end_date: Carbon,
     *     duration_days: int,
     *     base_cost: string,
     *     ev_charging_fee: string,
     *     total_cost: string
     * }
     *
     * @throws ValidationException
     */
    public function calculateInitialQuote(
        Garage $garage,
        string $vehicleType,
        string $rentalType,
        ?int $jumlahHari = null,
        float|int|string|null $batteryCapacityKwh = null,
        ?Carbon $startDate = null
    ): array {
        if (! in_array($vehicleType, Rental::VEHICLE_TYPES, true)) {
            throw ValidationException::withMessages([
                'vehicle_type' => 'Tipe kendaraan tidak valid.',
            ]);
        }

        if (! $this->isCompatible($vehicleType, $garage->type)) {
            throw ValidationException::withMessages([
                'garage_id' => 'Tipe garasi tidak kompatibel dengan tipe kendaraan yang dipilih.',
            ]);
        }

        if ($this->requiresBatteryCapacity($vehicleType) && ($batteryCapacityKwh === null || (float) $batteryCapacityKwh <= 0)) {
            throw ValidationException::withMessages([
                'battery_capacity_kwh' => 'Kapasitas baterai (kWh) wajib diisi dan lebih besar dari 0 untuk mobil listrik.',
            ]);
        }

        $start = ($startDate ?? Carbon::today('Asia/Jakarta'))->copy()->startOfDay();
        $durationDays = $this->calculateInitialDurationDays($rentalType, $start, $jumlahHari);
        $endDate = $this->calculateInitialEndDate($start, $durationDays);

        $baseCost = $rentalType === Rental::TYPE_BULANAN
            ? $this->calculateMonthlyBaseRate($garage)
            : $this->multiplyMoney($durationDays, $garage->daily_rate);

        $evChargingFee = $this->calculateEvChargingFee($garage, $vehicleType, $batteryCapacityKwh);
        $totalCost = $this->addMoney($baseCost, $evChargingFee);

        return [
            'start_date' => $start,
            'end_date' => $endDate,
            'duration_days' => $durationDays,
            'base_cost' => $baseCost,
            'ev_charging_fee' => $evChargingFee,
            'total_cost' => $totalCost,
        ];
    }

    /**
     * Multiply two numeric values with exact decimal arithmetic rounded to 2 decimal places.
     */
    public function multiplyMoney(float|int|string $left, float|int|string $right): string
    {
        $product = bcmul($this->normalizeDecimal($left, 4), $this->normalizeDecimal($right, 4), 6);

        return bcround($product, 2);
    }

    /**
     * Add two currency amounts with 2 decimal places.
     */
    public function addMoney(float|int|string $left, float|int|string $right): string
    {
        return bcadd($this->normalizeDecimal($left, 2), $this->normalizeDecimal($right, 2), 2);
    }

    private function normalizeDecimal(float|int|string $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
