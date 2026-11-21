@extends('layouts.app')

@section('title', 'Perpanjang Status Garasi — Admin')

@section('content')
    @php
        $typeLabels = [
            'sepeda' => 'Garasi Sepeda',
            'mobil' => 'Garasi Mobil',
            'mobil_listrik' => 'Garasi Mobil Listrik',
        ];
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Perpanjang Status Garasi</h1>
            <p class="mt-1 text-sm text-slate-600">
                Menampilkan seluruh garasi yang sedang aktif disewa hari ini ({{ $today->format('d M Y') }} WIB).
            </p>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Garasi Aktif Hari Ini ({{ $activeRentals->count() }})</h2>
            </div>

            @if ($activeRentals->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    Tidak ada garasi yang sedang aktif disewa hari ini.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Kode Garasi</th>
                                <th class="px-6 py-3">Tipe Garasi</th>
                                <th class="px-6 py-3">Penyewa</th>
                                <th class="px-6 py-3">Tipe Sewa</th>
                                <th class="px-6 py-3">Tanggal Mulai</th>
                                <th class="px-6 py-3">Tanggal Selesai</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($activeRentals as $rental)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-6 py-3.5 font-mono font-semibold text-slate-900">
                                        {{ $rental->garage->code }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-slate-700">
                                        {{ $typeLabels[$rental->garage->type] ?? $rental->garage->type }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 font-medium text-slate-900">
                                        {{ $rental->user->nama_lengkap }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5">
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-700">
                                            {{ $rental->rental_type === 'harian' ? 'Harian' : 'Bulanan' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 font-mono text-slate-700">
                                        {{ $rental->start_date->toDateString() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 font-mono font-semibold text-slate-900">
                                        {{ $rental->end_date->toDateString() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                        <a href="{{ route('admin.extensions.show', $rental) }}"
                                           class="inline-flex rounded-lg bg-slate-900 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800">
                                            Perpanjang
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
