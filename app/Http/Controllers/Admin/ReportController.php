<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\User;
use App\Services\RentalReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly RentalReportService $reportService
    ) {}

    public function index(Request $request): View
    {
        $report = $this->reportService->generate(
            $request->only(['year', 'month', 'garage_type', 'user_id'])
        );

        $tenants = User::query()
            ->penyewa()
            ->approved()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nik']);

        $currentYear = Carbon::today('Asia/Jakarta')->year;
        $yearExpr = DB::connection()->getDriverName() === 'pgsql'
            ? 'DISTINCT EXTRACT(YEAR FROM start_date)::int as year'
            : "DISTINCT CAST(strftime('%Y', start_date) AS INTEGER) as year";

        $existingYears = Rental::query()
            ->selectRaw($yearExpr)
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->all();

        $availableYears = collect([$currentYear, $currentYear - 1, $currentYear + 1, ...$existingYears, $report['filters']['year']])
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return view('admin.reports.index', [
            'report' => $report,
            'tenants' => $tenants,
            'availableYears' => $availableYears,
        ]);
    }
}
