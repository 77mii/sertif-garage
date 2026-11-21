<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGarageBatchRequest;
use App\Models\Garage;
use App\Services\GarageAvailabilityService;
use App\Services\GarageBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GarageController extends Controller
{
    public function __construct(
        private readonly GarageAvailabilityService $availabilityService,
        private readonly GarageBatchService $batchService
    ) {}

    public function index(): View
    {
        $this->availabilityService->syncStatuses();

        $garages = Garage::query()
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $totalGarages = $garages->count();
        $remainingCapacity = max(0, Garage::GLOBAL_MAX_CAPACITY - $totalGarages);

        return view('admin.garages.index', [
            'garages' => $garages,
            'totalGarages' => $totalGarages,
            'maxCapacity' => Garage::GLOBAL_MAX_CAPACITY,
            'remainingCapacity' => $remainingCapacity,
        ]);
    }

    public function store(StoreGarageBatchRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $created = $this->batchService->createBatch(
            type: (string) $validated['type'],
            quantity: (int) $validated['quantity'],
            dailyRate: $validated['daily_rate'],
            kwhRate: $validated['kwh_rate'] ?? null
        );

        $firstCode = $created->first()?->code ?? '';
        $lastCode = $created->last()?->code ?? '';
        $range = $firstCode === $lastCode ? $firstCode : "{$firstCode}–{$lastCode}";

        return redirect()
            ->route('admin.garages.index')
            ->with('success', "Berhasil membuat {$created->count()} garasi baru ({$range}).");
    }
}
