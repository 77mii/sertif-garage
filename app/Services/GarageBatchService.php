<?php

namespace App\Services;

use App\Models\Garage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GarageBatchService
{
    private const ADVISORY_LOCK_KEY = 999001;

    /**
     * Batch-create garages for a given type while enforcing the global 999 cap
     * and per-type incremental 3-digit codes (S001, C001, E001).
     *
     * @return Collection<int, Garage>
     *
     * @throws ValidationException
     */
    public function createBatch(
        string $type,
        int $quantity,
        float|int|string $dailyRate,
        float|int|string|null $kwhRate = null
    ): Collection {
        $isEv = $type === Garage::TYPE_MOBIL_LISTRIK;

        $validated = Validator::make(
            [
                'type' => $type,
                'quantity' => $quantity,
                'daily_rate' => $dailyRate,
                'kwh_rate' => $isEv ? $kwhRate : null,
            ],
            [
                'type' => ['required', 'string', Rule::in(Garage::TYPES)],
                'quantity' => ['required', 'integer', 'min:1'],
                'daily_rate' => ['required', 'numeric', 'gt:0'],
                'kwh_rate' => $isEv ? ['required', 'numeric', 'gt:0'] : ['nullable'],
            ]
        )->validate();

        return DB::transaction(function () use ($validated, $isEv): Collection {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(?)', [self::ADVISORY_LOCK_KEY]);
            } else {
                Garage::query()->lockForUpdate()->count();
            }

            $existingTotal = Garage::query()->count();
            $requestedQuantity = (int) $validated['quantity'];
            $remainingCapacity = max(0, Garage::GLOBAL_MAX_CAPACITY - $existingTotal);

            if ($existingTotal + $requestedQuantity > Garage::GLOBAL_MAX_CAPACITY) {
                throw ValidationException::withMessages([
                    'quantity' => "Total garasi melebihi batas maksimal global 999 unit. Sisa kapasitas tersedia: {$remainingCapacity}.",
                ]);
            }

            $garageType = (string) $validated['type'];
            $prefix = Garage::PREFIX_BY_TYPE[$garageType];

            $lastCode = Garage::query()
                ->where('type', $garageType)
                ->orderByDesc('code')
                ->value('code');

            $lastSequence = $lastCode !== null ? (int) substr((string) $lastCode, 1) : 0;

            $formattedDailyRate = number_format(round((float) $validated['daily_rate'], 2), 2, '.', '');
            $formattedKwhRate = $isEv
                ? number_format(round((float) $validated['kwh_rate'], 2), 2, '.', '')
                : null;

            $now = Carbon::now('Asia/Jakarta');
            $rows = [];
            $codes = [];

            for ($i = 1; $i <= $requestedQuantity; $i++) {
                $code = sprintf('%s%03d', $prefix, $lastSequence + $i);
                $codes[] = $code;
                $rows[] = [
                    'code' => $code,
                    'type' => $garageType,
                    'daily_rate' => $formattedDailyRate,
                    'kwh_rate' => $formattedKwhRate,
                    'status' => Garage::STATUS_AVAILABLE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 250) as $chunk) {
                Garage::query()->insert($chunk);
            }

            return Garage::query()
                ->whereIn('code', $codes)
                ->orderBy('code')
                ->get();
        });
    }
}
