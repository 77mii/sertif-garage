<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GarageAvailabilityService
{
    /**
     * Return today's date in Asia/Jakarta (WIB).
     */
    public function today(): Carbon
    {
        return Carbon::today('Asia/Jakarta');
    }

    /**
     * Lazily flip garages from 'used' back to 'available' when they have no
     * active rental covering today (start_date <= today AND end_date >= today).
     */
    public function syncStatuses(?Carbon $today = null): int
    {
        $date = ($today ?? $this->today())->toDateString();

        return Garage::query()
            ->where('status', Garage::STATUS_USED)
            ->whereDoesntHave('rentals', function (Builder $query) use ($date): void {
                $query->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            })
            ->update([
                'status' => Garage::STATUS_AVAILABLE,
                'updated_at' => Carbon::now('Asia/Jakarta'),
            ]);
    }

    /**
     * Sync statuses and retrieve available garages, optionally filtered by type.
     *
     * @return Collection<int, Garage>
     */
    public function getAvailableGarages(?string $type = null, ?Carbon $today = null): Collection
    {
        $this->syncStatuses($today);

        return Garage::query()
            ->available()
            ->when($type !== null, fn (Builder $query): Builder => $query->where('type', $type))
            ->orderBy('code')
            ->get();
    }

    /**
     * Sync statuses and retrieve all rentals currently active on the target date.
     *
     * @return Collection<int, Rental>
     */
    public function getActiveRentals(?Carbon $today = null): Collection
    {
        $targetDate = $today ?? $this->today();
        $this->syncStatuses($targetDate);

        return Rental::query()
            ->with(['garage', 'user'])
            ->activeOn($targetDate)
            ->orderBy('end_date')
            ->orderBy('id')
            ->get();
    }
}
