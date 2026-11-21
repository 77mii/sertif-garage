@extends('layouts.app')

@section('title', 'Laporan Sewa Garasi — Admin')

@section('content')
    @php
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
        $garageTypeLabels = [
            'sepeda' => 'Garasi Sepeda',
            'mobil' => 'Garasi Mobil',
            'mobil_listrik' => 'Garasi Mobil Listrik',
        ];
        $filters = $report['filters'];
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Laporan Sewa Garasi</h1>
            <p class="mt-1 text-sm text-slate-600">
                Ringkasan agregat transaksi dan pendapatan. Seluruh pendapatan (termasuk perpanjangan) diatribusikan ke <strong>Tanggal Mulai Sewa</strong>.
            </p>
        </div>

        {{-- Filter Gabungan --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="year" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Tahun</label>
                    <select id="year"
                            name="year"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                        @foreach ($availableYears as $yearOption)
                            <option value="{{ $yearOption }}" @selected($filters['year'] === $yearOption)>{{ $yearOption }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="month" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Bulan</label>
                    <select id="month"
                            name="month"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                        <option value="">Semua Bulan</option>
                        @foreach ($monthNames as $monthNumber => $monthLabel)
                            <option value="{{ $monthNumber }}" @selected($filters['month'] === $monthNumber)>{{ $monthLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="garage_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Tipe Garasi</label>
                    <select id="garage_type"
                            name="garage_type"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                        <option value="">Semua Tipe Garasi</option>
                        @foreach ($garageTypeLabels as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected($filters['garage_type'] === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="user_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Penyewa</label>
                    <select id="user_id"
                            name="user_id"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                        <option value="">Semua Penyewa</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected($filters['user_id'] === $tenant->id)>
                                {{ $tenant->nama_lengkap }} ({{ $tenant->nik }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Terapkan
                    </button>
                    <a href="{{ route('admin.reports.index') }}"
                       class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        {{-- 1. KPI Totals --}}
        <section class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Transaksi Sewa</p>
                <p class="mt-2 tabular-nums text-3xl font-bold text-slate-900">
                    {{ number_format($report['kpi']['rental_count'], 0, ',', '.') }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Pendapatan</p>
                <p class="mt-2 tabular-nums text-3xl font-bold text-emerald-700">
                    Rp {{ number_format((float) $report['kpi']['total_revenue'], 0, ',', '.') }}
                </p>
            </div>
        </section>

        {{-- 2 & 3. Per Bulan dan Per Hari --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ringkasan Per Bulan (Tahun {{ $filters['year'] }})</h2>
                </div>
                @if ($report['per_bulan'] === [])
                    <div class="px-6 py-8 text-center text-sm text-slate-500">Tidak ada data untuk filter terpilih.</div>
                @else
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Bulan</th>
                                <th class="px-6 py-3 text-right">Jumlah Sewa</th>
                                <th class="px-6 py-3 text-right">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['per_bulan'] as $row)
                                <tr>
                                    <td class="px-6 py-3 font-medium text-slate-900">{{ $monthNames[$row['month']] ?? $row['month'] }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums text-slate-700">{{ $row['rental_count'] }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums font-semibold text-slate-900">
                                        Rp {{ number_format((float) $row['total_revenue'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-900">
                        Ringkasan Per Hari
                        @if ($filters['month'])
                            ({{ $monthNames[$filters['month']] }} {{ $filters['year'] }})
                        @endif
                    </h2>
                </div>
                @if ($filters['month'] === null)
                    <div class="px-6 py-8 text-center text-sm text-slate-500">
                        Pilih filter <strong>Bulan</strong> di atas untuk melihat ringkasan harian.
                    </div>
                @elseif ($report['per_hari'] === [])
                    <div class="px-6 py-8 text-center text-sm text-slate-500">Tidak ada data harian pada bulan terpilih.</div>
                @else
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Tanggal Mulai</th>
                                <th class="px-6 py-3 text-right">Jumlah Sewa</th>
                                <th class="px-6 py-3 text-right">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['per_hari'] as $row)
                                <tr>
                                    <td class="px-6 py-3 font-mono text-slate-900">{{ $row['date'] }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums text-slate-700">{{ $row['rental_count'] }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums font-semibold text-slate-900">
                                        Rp {{ number_format((float) $row['total_revenue'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>

        {{-- 4 & 5. Per Tipe Garasi dan Per Penyewa --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ringkasan Per Tipe Garasi</h2>
                </div>
                @if ($report['per_tipe_garasi'] === [])
                    <div class="px-6 py-8 text-center text-sm text-slate-500">Tidak ada data untuk filter terpilih.</div>
                @else
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Tipe Garasi</th>
                                <th class="px-6 py-3 text-right">Jumlah Sewa</th>
                                <th class="px-6 py-3 text-right">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['per_tipe_garasi'] as $row)
                                <tr>
                                    <td class="px-6 py-3 font-medium text-slate-900">
                                        {{ $garageTypeLabels[$row['garage_type']] ?? $row['garage_type'] }}
                                    </td>
                                    <td class="px-6 py-3 text-right tabular-nums text-slate-700">{{ $row['rental_count'] }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums font-semibold text-slate-900">
                                        Rp {{ number_format((float) $row['total_revenue'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Ringkasan Per Penyewa</h2>
                </div>
                @if ($report['per_penyewa'] === [])
                    <div class="px-6 py-8 text-center text-sm text-slate-500">Tidak ada data untuk filter terpilih.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-6 py-3">Penyewa</th>
                                    <th class="px-6 py-3">NIK</th>
                                    <th class="px-6 py-3 text-right">Jumlah Sewa</th>
                                    <th class="px-6 py-3 text-right">Total Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($report['per_penyewa'] as $row)
                                    <tr>
                                        <td class="px-6 py-3 font-medium text-slate-900">{{ $row['nama_lengkap'] }}</td>
                                        <td class="px-6 py-3 font-mono text-xs text-slate-600">{{ $row['nik'] ?? '-' }}</td>
                                        <td class="px-6 py-3 text-right tabular-nums text-slate-700">{{ $row['rental_count'] }}</td>
                                        <td class="px-6 py-3 text-right tabular-nums font-semibold text-slate-900">
                                            Rp {{ number_format((float) $row['total_revenue'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
