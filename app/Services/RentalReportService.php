<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RentalReportService
{
    /**
     * Generate summary-only rental aggregates anchored strictly on rentals.start_date.
     *
     * @param  array{
     *     year?: int|string|null,
     *     month?: int|string|null,
     *     garage_type?: string|null,
     *     user_id?: int|string|null
     * }  $filters
     * @return array{
     *     filters: array{
     *         year: int,
     *         month: int|null,
     *         garage_type: string|null,
     *         user_id: int|null
     *     },
     *     kpi: array{
     *         rental_count: int,
     *         total_revenue: string
     *     },
     *     per_bulan: list<array{
     *         month: int,
     *         rental_count: int,
     *         total_revenue: string
     *     }>,
     *     per_hari: list<array{
     *         date: string,
     *         rental_count: int,
     *         total_revenue: string
     *     }>,
     *     per_tipe_garasi: list<array{
     *         garage_type: string,
     *         rental_count: int,
     *         total_revenue: string
     *     }>,
     *     per_penyewa: list<array{
     *         user_id: int,
     *         nama_lengkap: string,
     *         nik: string|null,
     *         rental_count: int,
     *         total_revenue: string
     *     }>
     * }
     *
     * @throws ValidationException
     */
    public function generate(array $filters = []): array
    {
        $normalized = [
            'year' => $this->emptyToNull($filters['year'] ?? null),
            'month' => $this->emptyToNull($filters['month'] ?? null),
            'garage_type' => $this->emptyToNull($filters['garage_type'] ?? null),
            'user_id' => $this->emptyToNull($filters['user_id'] ?? null),
        ];

        $validated = Validator::make($normalized, [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'garage_type' => ['nullable', 'string', Rule::in(Garage::TYPES)],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $resolvedFilters = [
            'year' => isset($validated['year'])
                ? (int) $validated['year']
                : Carbon::today('Asia/Jakarta')->year,
            'month' => isset($validated['month']) ? (int) $validated['month'] : null,
            'garage_type' => isset($validated['garage_type']) ? (string) $validated['garage_type'] : null,
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
        ];

        $kpiRow = $this->baseQuery($resolvedFilters)
            ->selectRaw('COUNT(rentals.id) as rental_count, COALESCE(SUM(rentals.total_cost), 0) as total_revenue')
            ->first();

        $monthExpr = $this->monthExpression();
        $perBulan = $this->baseQuery($resolvedFilters)
            ->selectRaw("{$monthExpr} as month, COUNT(rentals.id) as rental_count, COALESCE(SUM(rentals.total_cost), 0) as total_revenue")
            ->groupByRaw($monthExpr)
            ->orderByRaw("{$monthExpr} ASC")
            ->get()
            ->map(fn (object $row): array => [
                'month' => (int) $row->month,
                'rental_count' => (int) $row->rental_count,
                'total_revenue' => $this->formatCurrency($row->total_revenue),
            ])
            ->values()
            ->all();

        $perHari = [];

        if ($resolvedFilters['month'] !== null) {
            $dateExpr = $this->dateExpression();
            $perHari = $this->baseQuery($resolvedFilters)
                ->selectRaw("{$dateExpr} as date, COUNT(rentals.id) as rental_count, COALESCE(SUM(rentals.total_cost), 0) as total_revenue")
                ->groupByRaw($dateExpr)
                ->orderByRaw("{$dateExpr} ASC")
                ->get()
                ->map(fn (object $row): array => [
                    'date' => (string) $row->date,
                    'rental_count' => (int) $row->rental_count,
                    'total_revenue' => $this->formatCurrency($row->total_revenue),
                ])
                ->values()
                ->all();
        }

        $perTipeGarasi = $this->baseQuery($resolvedFilters)
            ->selectRaw('garages.type as garage_type, COUNT(rentals.id) as rental_count, COALESCE(SUM(rentals.total_cost), 0) as total_revenue')
            ->groupBy('garages.type')
            ->orderBy('garages.type')
            ->get()
            ->map(fn (object $row): array => [
                'garage_type' => (string) $row->garage_type,
                'rental_count' => (int) $row->rental_count,
                'total_revenue' => $this->formatCurrency($row->total_revenue),
            ])
            ->values()
            ->all();

        $perPenyewa = $this->baseQuery($resolvedFilters)
            ->selectRaw('users.id as user_id, users.nama_lengkap, users.nik, COUNT(rentals.id) as rental_count, COALESCE(SUM(rentals.total_cost), 0) as total_revenue')
            ->groupBy('users.id', 'users.nama_lengkap', 'users.nik')
            ->orderByDesc('total_revenue')
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(fn (object $row): array => [
                'user_id' => (int) $row->user_id,
                'nama_lengkap' => (string) $row->nama_lengkap,
                'nik' => $row->nik !== null ? (string) $row->nik : null,
                'rental_count' => (int) $row->rental_count,
                'total_revenue' => $this->formatCurrency($row->total_revenue),
            ])
            ->values()
            ->all();

        return [
            'filters' => $resolvedFilters,
            'kpi' => [
                'rental_count' => (int) ($kpiRow->rental_count ?? 0),
                'total_revenue' => $this->formatCurrency($kpiRow->total_revenue ?? 0),
            ],
            'per_bulan' => $perBulan,
            'per_hari' => $perHari,
            'per_tipe_garasi' => $perTipeGarasi,
            'per_penyewa' => $perPenyewa,
        ];
    }

    /**
     * Build the filtered base query anchored on rentals.start_date.
     *
     * @param  array{
     *     year: int,
     *     month: int|null,
     *     garage_type: string|null,
     *     user_id: int|null
     * }  $filters
     * @return Builder<Rental>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Rental::query()
            ->join('garages', 'garages.id', '=', 'rentals.garage_id')
            ->join('users', 'users.id', '=', 'rentals.user_id')
            ->whereYear('rentals.start_date', $filters['year']);

        if ($filters['month'] !== null) {
            $query->whereMonth('rentals.start_date', $filters['month']);
        }

        if ($filters['garage_type'] !== null) {
            $query->where('garages.type', $filters['garage_type']);
        }

        if ($filters['user_id'] !== null) {
            $query->where('rentals.user_id', $filters['user_id']);
        }

        return $query;
    }

    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(MONTH FROM rentals.start_date)::int',
            'sqlite' => "CAST(strftime('%m', rentals.start_date) AS INTEGER)",
            default => 'MONTH(rentals.start_date)',
        };
    }

    private function dateExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(rentals.start_date, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', rentals.start_date)",
            default => "DATE_FORMAT(rentals.start_date, '%Y-%m-%d')",
        };
    }

    private function formatCurrency(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function emptyToNull(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}
