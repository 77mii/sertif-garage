<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtendRentalRequest;
use App\Models\Rental;
use App\Services\GarageAvailabilityService;
use App\Services\RentalExtensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ExtensionController extends Controller
{
    public function __construct(
        private readonly GarageAvailabilityService $availabilityService,
        private readonly RentalExtensionService $extensionService
    ) {}

    public function index(): View
    {
        $today = $this->availabilityService->today();
        $activeRentals = $this->availabilityService->getActiveRentals($today);

        return view('admin.extensions.index', [
            'activeRentals' => $activeRentals,
            'today' => $today,
        ]);
    }

    public function show(Rental $rental): View|RedirectResponse
    {
        $today = $this->availabilityService->today();
        $this->availabilityService->syncStatuses($today);

        if (! $rental->isActiveOn($today)) {
            return redirect()
                ->route('admin.extensions.index')
                ->with('error', 'Sewa garasi tidak aktif hari ini sehingga tidak dapat diperpanjang.');
        }

        $rental->load(['garage', 'user', 'payments' => fn ($query) => $query->with('recorder')->orderBy('id')]);

        return view('admin.extensions.show', [
            'rental' => $rental,
            'today' => $today,
            'minHarianEndDate' => $rental->end_date->copy()->addDay()->toDateString(),
        ]);
    }

    public function store(ExtendRentalRequest $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validated();
        $proofPath = $request->file('bukti_bayar')->store('payment_proofs', 'public');

        try {
            $payment = $this->extensionService->extend($rental, $request->user(), [
                'new_end_date' => $validated['new_end_date'] ?? null,
                'added_months' => $validated['added_months'] ?? null,
                'proof_image_path' => $proofPath,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($proofPath);

            throw $exception;
        }

        $formattedCost = number_format((float) $payment->amount, 0, ',', '.');

        return redirect()
            ->route('admin.extensions.show', $rental)
            ->with(
                'success',
                "Perpanjangan berhasil hingga {$payment->new_end_date->toDateString()} (+{$payment->added_days} hari, biaya tambahan Rp {$formattedCost})."
            );
    }
}
