<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RentalExtensionService
{
    public function __construct(
        private readonly GarageAvailabilityService $availabilityService,
        private readonly RentalPricingService $pricingService
    ) {}

    /**
     * Calculate and validate an extension quote without persisting changes.
     *
     * @param  array{
     *     new_end_date?: mixed,
     *     added_months?: mixed
     * }  $data
     * @return array{
     *     previous_end_date: Carbon,
     *     new_end_date: Carbon,
     *     added_days: int,
     *     added_months: int|null,
     *     ev_charging_fee: string,
     *     sisa_biaya: string
     * }
     *
     * @throws ValidationException
     */
    public function calculateExtensionQuote(Rental $rental, array $data, ?Carbon $today = null): array
    {
        $targetDate = $today ?? $this->availabilityService->today();

        if (! $rental->isActiveOn($targetDate)) {
            throw ValidationException::withMessages([
                'rental_id' => 'Sewa garasi tidak sedang aktif hari ini sehingga tidak dapat diperpanjang.',
            ]);
        }

        $rental->loadMissing('garage');
        $oldEndDate = $rental->end_date->copy()->startOfDay();

        if ($rental->rental_type === Rental::TYPE_HARIAN) {
            $validated = Validator::make($data, [
                'new_end_date' => ['required', 'date'],
            ])->validate();

            $newEndDate = Carbon::parse((string) $validated['new_end_date'], 'Asia/Jakarta')->startOfDay();

            if ($newEndDate->lte($oldEndDate)) {
                throw ValidationException::withMessages([
                    'new_end_date' => 'Tanggal selesai baru harus lebih besar dari tanggal selesai saat ini.',
                ]);
            }

            $addedDays = (int) $oldEndDate->diffInDays($newEndDate);
            $sisaBiaya = $this->pricingService->multiplyMoney($addedDays, $rental->garage->daily_rate);

            return [
                'previous_end_date' => $oldEndDate,
                'new_end_date' => $newEndDate,
                'added_days' => $addedDays,
                'added_months' => null,
                'ev_charging_fee' => '0.00',
                'sisa_biaya' => $sisaBiaya,
            ];
        }

        if ($rental->rental_type === Rental::TYPE_BULANAN) {
            $validated = Validator::make($data, [
                'added_months' => ['required', 'integer', 'min:1'],
            ])->validate();

            $addedMonths = (int) $validated['added_months'];
            $cursor = $oldEndDate->copy();

            for ($i = 0; $i < $addedMonths; $i++) {
                $cycleStart = $cursor->copy()->addDay();
                $cursor->addDays($cycleStart->daysInMonth);
            }

            $newEndDate = $cursor;
            $addedDays = (int) $oldEndDate->diffInDays($newEndDate);
            $monthlyBaseRate = $this->pricingService->calculateMonthlyBaseRate($rental->garage);
            $sisaBiaya = $this->pricingService->multiplyMoney($addedMonths, $monthlyBaseRate);

            return [
                'previous_end_date' => $oldEndDate,
                'new_end_date' => $newEndDate,
                'added_days' => $addedDays,
                'added_months' => $addedMonths,
                'ev_charging_fee' => '0.00',
                'sisa_biaya' => $sisaBiaya,
            ];
        }

        throw ValidationException::withMessages([
            'rental_type' => 'Tipe sewa tidak valid.',
        ]);
    }

    /**
     * Extend an active rental, persist an extension payment record, and update the parent rental.
     *
     * @param  array{
     *     new_end_date?: mixed,
     *     added_months?: mixed,
     *     proof_image_path?: mixed
     * }  $data
     *
     * @throws ValidationException
     */
    public function extend(Rental $rental, User $admin, array $data): RentalPayment
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'recorded_by' => 'Hanya admin yang dapat memperpanjang status garasi.',
            ]);
        }

        $proofValidated = Validator::make($data, [
            'proof_image_path' => ['required', 'string', 'max:500'],
        ])->validate();

        $payment = DB::transaction(function () use ($rental, $admin, $data, $proofValidated): RentalPayment {
            $today = $this->availabilityService->today();
            $this->availabilityService->syncStatuses($today);

            $lockedRental = Rental::query()
                ->with('garage')
                ->whereKey($rental->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quote = $this->calculateExtensionQuote($lockedRental, $data, $today);

            $payment = RentalPayment::query()->create([
                'rental_id' => $lockedRental->id,
                'payment_type' => RentalPayment::TYPE_EXTENSION,
                'amount' => $quote['sisa_biaya'],
                'added_days' => $quote['added_days'],
                'added_months' => $quote['added_months'],
                'previous_end_date' => $quote['previous_end_date']->toDateString(),
                'new_end_date' => $quote['new_end_date']->toDateString(),
                'proof_image_path' => trim((string) $proofValidated['proof_image_path']),
                'recorded_by' => $admin->id,
            ]);

            $lockedRental->end_date = $quote['new_end_date']->toDateString();
            $lockedRental->total_cost = $this->pricingService->addMoney(
                $lockedRental->total_cost,
                $quote['sisa_biaya']
            );
            $lockedRental->save();

            return $payment;
        });

        $rental->refresh();

        return $payment->load(['rental.garage', 'recorder']);
    }
}
