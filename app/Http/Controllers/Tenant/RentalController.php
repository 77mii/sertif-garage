<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRentalRequest;
use App\Services\GarageAvailabilityService;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class RentalController extends Controller
{
    public function __construct(
        private readonly GarageAvailabilityService $availabilityService,
        private readonly RentalPricingService $pricingService,
        private readonly RentalService $rentalService
    ) {}

    public function create(): View
    {
        $today = $this->availabilityService->today();
        $availableGarages = $this->availabilityService->getAvailableGarages(null, $today);

        $garagePayload = $availableGarages
            ->map(fn ($garage): array => [
                'id' => $garage->id,
                'code' => $garage->code,
                'type' => $garage->type,
                'daily_rate' => (float) $garage->daily_rate,
                'kwh_rate' => $garage->kwh_rate !== null ? (float) $garage->kwh_rate : null,
            ])
            ->values()
            ->all();

        return view('tenant.rentals.create', [
            'today' => $today,
            'daysInMonth' => $today->daysInMonth,
            'maxDailyDays' => $this->pricingService->maxDailyDays($today),
            'availableGarages' => $availableGarages,
            'garagePayload' => $garagePayload,
            'compatibilityMatrix' => RentalPricingService::ALLOWED_GARAGE_TYPES,
        ]);
    }

    public function store(StoreRentalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $proofPath = $request->file('bukti_bayar')->store('payment_proofs', 'public');

        try {
            $rental = $this->rentalService->createRental($request->user(), [
                'garage_id' => $validated['garage_id'],
                'vehicle_type' => $validated['vehicle_type'],
                'license_plate' => $validated['license_plate'] ?? null,
                'vehicle_brand_type' => $validated['vehicle_brand_type'],
                'battery_capacity_kwh' => $validated['battery_capacity_kwh'] ?? null,
                'rental_type' => $validated['rental_type'],
                'jumlah_hari' => $validated['jumlah_hari'] ?? null,
                'proof_image_path' => $proofPath,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($proofPath);

            throw $exception;
        }

        return redirect()
            ->route('tenant.rentals.create')
            ->with('success', "Sewa garasi {$rental->garage->code} berhasil dikonfirmasi.")
            ->with('rental_confirmation', [
                'garage_code' => $rental->garage->code,
                'garage_type' => $rental->garage->type,
                'vehicle_type' => $rental->vehicle_type,
                'vehicle_brand_type' => $rental->vehicle_brand_type,
                'license_plate' => $rental->license_plate,
                'rental_type' => $rental->rental_type,
                'start_date' => $rental->start_date->toDateString(),
                'end_date' => $rental->end_date->toDateString(),
                'duration_days' => $rental->initial_duration_days,
                'ev_charging_fee' => $rental->ev_charging_fee,
                'total_cost' => $rental->total_cost,
            ]);
    }
}
