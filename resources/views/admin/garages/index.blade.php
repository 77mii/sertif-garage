@extends('layouts.app')

@section('title', 'Buat Garasi Baru — Admin')

@section('content')
    @php
        $typeLabels = [
            'sepeda' => 'Garasi Sepeda',
            'mobil' => 'Garasi Mobil',
            'mobil_listrik' => 'Garasi Mobil Listrik',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Buat Garasi Baru</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Tambahkan unit garasi
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-right shadow-xs">
                <p class="text-xs font-medium text-slate-500">Sisa Kapasitas Global</p>
                <p class="tabular-nums text-lg font-bold text-slate-900">
                    {{ $remainingCapacity }} <span class="text-sm font-normal text-slate-500">/ {{ $maxCapacity }} unit</span>
                </p>
                <p class="text-[11px] text-slate-500">Terpakai: {{ $totalGarages }} unit</p>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            <h2 class="text-base font-semibold text-slate-900">Form Tambah Batch Garasi</h2>

            <form method="POST" action="{{ route('admin.garages.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf

                <div>
                    <label for="type" class="block text-sm font-medium text-slate-700">Tipe Garasi</label>
                    <select id="type"
                            name="type"
                            required
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                        <option value="sepeda" @selected(old('type', 'sepeda') === 'sepeda')>Garasi Sepeda (S)</option>
                        <option value="mobil" @selected(old('type') === 'mobil')>Garasi Mobil (C)</option>
                        <option value="mobil_listrik" @selected(old('type') === 'mobil_listrik')>Garasi Mobil Listrik (E)</option>
                    </select>
                </div>

                <div>
                    <label for="quantity" class="block text-sm font-medium text-slate-700">Jumlah Unit</label>
                    <input id="quantity"
                           name="quantity"
                           type="number"
                           min="1"
                           max="{{ max(1, $remainingCapacity) }}"
                           step="1"
                           value="{{ old('quantity', 1) }}"
                           required
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div>
                    <label for="daily_rate" class="block text-sm font-medium text-slate-700">Tarif Harian (Rp)</label>
                    <input id="daily_rate"
                           name="daily_rate"
                           type="number"
                           step="0.01"
                           min="1"
                           value="{{ old('daily_rate') }}"
                           required
                           placeholder="50000"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div id="kwh_rate_wrapper" class="{{ old('type') === 'mobil_listrik' ? '' : 'hidden' }}">
                    <label for="kwh_rate" class="block text-sm font-medium text-slate-700">Tarif per kWh (Rp)</label>
                    <input id="kwh_rate"
                           name="kwh_rate"
                           type="number"
                           step="0.01"
                           min="1"
                           value="{{ old('kwh_rate') }}"
                           placeholder="2500"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div class="flex items-end sm:col-span-2 lg:col-span-4">
                    <button type="submit"
                            @disabled($remainingCapacity === 0)
                            class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400">
                        Buat Garasi
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Daftar Inventaris Garasi ({{ $totalGarages }} Unit)</h2>
                <p class="text-xs text-slate-500">Status garasi disinkronkan secara otomatis terhadap tanggal hari ini (WIB).</p>
            </div>

            @if ($garages->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    Belum ada garasi yang terdaftar. Gunakan form di atas untuk membuat garasi baru.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Kode</th>
                                <th class="px-6 py-3">Tipe Garasi</th>
                                <th class="px-6 py-3 text-right">Tarif Harian</th>
                                <th class="px-6 py-3 text-right">Tarif per kWh</th>
                                <th class="px-6 py-3 text-center">Status Hari Ini</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($garages as $garage)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-6 py-3.5 font-mono font-semibold text-slate-900">
                                        {{ $garage->code }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-slate-700">
                                        {{ $typeLabels[$garage->type] ?? $garage->type }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-900">
                                        Rp {{ number_format((float) $garage->daily_rate, 0, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-700">
                                        @if ($garage->kwh_rate !== null)
                                            Rp {{ number_format((float) $garage->kwh_rate, 0, ',', '.') }}
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-center">
                                        @if ($garage->isAvailable())
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                Tersedia
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                Digunakan
                                            </span>
                                        @endif
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

@push('scripts')
    <script>
        (function () {
            const typeEl = document.getElementById('type');
            const kwhWrapper = document.getElementById('kwh_rate_wrapper');
            const kwhInput = document.getElementById('kwh_rate');

            function toggleKwhRate() {
                const isEv = typeEl.value === 'mobil_listrik';
                kwhWrapper.classList.toggle('hidden', !isEv);
                kwhInput.disabled = !isEv;
                kwhInput.required = isEv;
            }

            typeEl.addEventListener('change', toggleKwhRate);
            toggleKwhRate();
        })();
    </script>
@endpush
