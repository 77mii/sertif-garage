<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RentalService
{
    public function __construct(
        private readonly GarageAvailabilityService $availabilityService,
        private readonly RentalPricingService $pricingService
    ) {}

    /**
     * Create a new rental booking with pessimistic locking and server-side pricing validation.
     *
     * @param  array{
     *     garage_id?: mixed,
     *     vehicle_type?: mixed,
     *     license_plate?: mixed,
     *     vehicle_brand_type?: mixed,
     *     battery_capacity_kwh?: mixed,
     *     rental_type?: mixed,
     *     start_date?: mixed,
     *     jumlah_hari?: mixed,
     *     proof_image_path?: mixed
     * }  $data
     *
     * @throws ValidationException
     */
    public function createRental(User $tenant, array $data): Rental
    {
        if (! $tenant->isPenyewa() || ! $tenant->isApproved()) {
            throw ValidationException::withMessages([
                'user_id' => 'Hanya penyewa terverifikasi yang dapat melakukan sewa baru.',
            ]);
        }

        $today = $this->availabilityService->today();
        $todayString = $today->toDateString();
        $endOfMonthString = $today->copy()->endOfMonth()->toDateString();

        $vehicleType = isset($data['vehicle_type']) ? (string) $data['vehicle_type'] : '';
        $rentalType = isset($data['rental_type']) ? (string) $data['rental_type'] : '';

        $startDateRules = $rentalType === Rental::TYPE_HARIAN
            ? ['nullable', 'date', "after_or_equal:{$todayString}", "before_or_equal:{$endOfMonthString}"]
            : ['nullable', 'date', "date_equals:{$todayString}"];

        $validated = Validator::make(
            $data,
            [
                'garage_id' => ['required', 'integer', 'exists:garages,id'],
                'vehicle_type' => ['required', 'string', Rule::in(Rental::VEHICLE_TYPES)],
                'license_plate' => $this->pricingService->requiresLicensePlate($vehicleType)
                    ? ['required', 'string', 'max:30']
                    : ['nullable', 'string', 'max:30'],
                'vehicle_brand_type' => ['required', 'string', 'max:255'],
                'battery_capacity_kwh' => $this->pricingService->requiresBatteryCapacity($vehicleType)
                    ? ['required', 'numeric', 'gt:0']
                    : ['nullable'],
                'rental_type' => ['required', 'string', Rule::in(Rental::RENTAL_TYPES)],
                'start_date' => $startDateRules,
                'jumlah_hari' => $rentalType === Rental::TYPE_HARIAN
                    ? ['required', 'integer', 'min:1']
                    : ['nullable'],
                'proof_image_path' => ['required', 'string', 'max:500'],
            ]
        )->validate();

        return DB::transaction(function () use ($tenant, $validated): Rental {
            $today = $this->availabilityService->today();
            $this->availabilityService->syncStatuses($today);

            $garage = Garage::query()
                ->whereKey((int) $validated['garage_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $hasBlockingRental = Rental::query()
                ->where('garage_id', $garage->id)
                ->whereDate('end_date', '>=', $today->toDateString())
                ->exists();

            if (! $garage->isAvailable() || $hasBlockingRental) {
                throw ValidationException::withMessages([
                    'garage_id' => 'Garasi yang dipilih sedang digunakan dan tidak tersedia.',
                ]);
            }

            $resolvedVehicleType = (string) $validated['vehicle_type'];
            $resolvedRentalType = (string) $validated['rental_type'];
            $resolvedStartDate = isset($validated['start_date']) && $validated['start_date'] !== ''
                ? Carbon::parse((string) $validated['start_date'], 'Asia/Jakarta')->startOfDay()
                : $today->copy();
            $jumlahHari = $resolvedRentalType === Rental::TYPE_HARIAN
                ? (int) $validated['jumlah_hari']
                : null;
            $batteryCapacityKwh = $this->pricingService->requiresBatteryCapacity($resolvedVehicleType)
                ? number_format(round((float) $validated['battery_capacity_kwh'], 2), 2, '.', '')
                : null;
            $licensePlate = $this->pricingService->requiresLicensePlate($resolvedVehicleType)
                ? trim((string) $validated['license_plate'])
                : null;

            $quote = $this->pricingService->calculateInitialQuote(
                garage: $garage,
                vehicleType: $resolvedVehicleType,
                rentalType: $resolvedRentalType,
                jumlahHari: $jumlahHari,
                batteryCapacityKwh: $batteryCapacityKwh,
                startDate: $resolvedStartDate
            );

            $rental = Rental::query()->create([
                'user_id' => $tenant->id,
                'garage_id' => $garage->id,
                'vehicle_type' => $resolvedVehicleType,
                'license_plate' => $licensePlate,
                'vehicle_brand_type' => trim((string) $validated['vehicle_brand_type']),
                'battery_capacity_kwh' => $batteryCapacityKwh,
                'rental_type' => $resolvedRentalType,
                'start_date' => $quote['start_date']->toDateString(),
                'end_date' => $quote['end_date']->toDateString(),
                'initial_duration_days' => $quote['duration_days'],
                'ev_charging_fee' => $quote['ev_charging_fee'],
                'total_cost' => $quote['total_cost'],
            ]);

            RentalPayment::query()->create([
                'rental_id' => $rental->id,
                'payment_type' => RentalPayment::TYPE_INITIAL,
                'amount' => $quote['total_cost'],
                'added_days' => $quote['duration_days'],
                'added_months' => null,
                'previous_end_date' => null,
                'new_end_date' => $quote['end_date']->toDateString(),
                'proof_image_path' => trim((string) $validated['proof_image_path']),
                'recorded_by' => $tenant->id,
            ]);

            $garage->status = Garage::STATUS_USED;
            $garage->save();

            return $rental->load(['garage', 'user', 'payments']);
        });
    }
}
