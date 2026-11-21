@extends('layouts.app')

@section('title', 'Perpanjang Sewa '.$rental->garage->code.' — Admin')

@section('content')
    @php
        $typeLabels = [
            'sepeda' => 'Garasi Sepeda',
            'mobil' => 'Garasi Mobil',
            'mobil_listrik' => 'Garasi Mobil Listrik',
        ];
        $isHarian = $rental->rental_type === \App\Models\Rental::TYPE_HARIAN;
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('admin.extensions.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-900">
                    ← Kembali ke Daftar Garasi Aktif
                </a>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                    Perpanjang Sewa Garasi {{ $rental->garage->code }}
                </h1>
            </div>
            <span class="inline-flex rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                Tipe Sewa: {{ $isHarian ? 'Harian' : 'Bulanan' }}
            </span>
        </div>

        {{-- Ringkasan Sewa Aktif --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            <h2 class="text-base font-semibold text-slate-900">Informasi Sewa Saat Ini</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-slate-500">Kode &amp; Tipe Garasi</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">
                        <span class="font-mono">{{ $rental->garage->code }}</span> — {{ $typeLabels[$rental->garage->type] ?? $rental->garage->type }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Penyewa</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">{{ $rental->user->nama_lengkap }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Kendaraan</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">
                        {{ $rental->vehicle_brand_type }}
                        @if ($rental->license_plate)
                            ({{ $rental->license_plate }})
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Tanggal Mulai Sewa</dt>
                    <dd class="mt-0.5 font-mono font-medium text-slate-900">{{ $rental->start_date->toDateString() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Tanggal Selesai Saat Ini</dt>
                    <dd class="mt-0.5 font-mono font-bold text-slate-900">{{ $rental->end_date->toDateString() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Tarif Harian Garasi</dt>
                    <dd class="mt-0.5 tabular-nums font-semibold text-slate-900">
                        Rp {{ number_format((float) $rental->garage->daily_rate, 0, ',', '.') }}/hari
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Total Biaya Tercatat</dt>
                    <dd class="mt-0.5 tabular-nums font-bold text-slate-900">
                        Rp {{ number_format((float) $rental->total_cost, 0, ',', '.') }}
                    </dd>
                </div>
            </dl>
        </section>

        {{-- Form Perpanjangan --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            <h2 class="text-base font-semibold text-slate-900">Form Perpanjangan</h2>

            <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs font-medium text-sky-900">
                Catatan: Biaya pengisian listrik (EV) pada perpanjangan adalah <strong>Rp 0</strong> (hanya dikenakan sekali saat sewa awal). Memperpendek tanggal sewa tidak diperbolehkan.
            </div>

            <form method="POST"
                  action="{{ route('admin.extensions.store', $rental) }}"
                  enctype="multipart/form-data"
                  class="mt-5 space-y-5">
                @csrf

                @if ($isHarian)
                    <div>
                        <label for="new_end_date" class="block text-sm font-medium text-slate-700">
                            Tanggal Selesai Baru (Minimal {{ $minHarianEndDate }})
                        </label>
                        <input id="new_end_date"
                               name="new_end_date"
                               type="date"
                               min="{{ $minHarianEndDate }}"
                               value="{{ old('new_end_date', $minHarianEndDate) }}"
                               required
                               class="mt-1 block w-full max-w-xs rounded-lg border border-slate-300 px-3.5 py-2.5 font-mono text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                    </div>
                @else
                    <div>
                        <label for="added_months" class="block text-sm font-medium text-slate-700">
                            Jumlah Bulan Tambahan (Kelipatan Bulan Penuh)
                        </label>
                        <input id="added_months"
                               name="added_months"
                               type="number"
                               min="1"
                               step="1"
                               value="{{ old('added_months', 1) }}"
                               required
                               class="mt-1 block w-full max-w-xs rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">Tarif perpanjangan bulanan: jumlah bulan × (25 × tarif harian).</p>
                    </div>
                @endif

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Tanggal Selesai Baru</dt>
                            <dd id="preview_new_end_date" class="font-mono font-semibold text-slate-900">—</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Hari Tambahan</dt>
                            <dd id="preview_added_days" class="tabular-nums font-medium text-slate-900">0 hari</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Biaya Pengisian Listrik (EV)</dt>
                            <dd class="tabular-nums font-medium text-slate-900">Rp 0</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-200 pt-2.5">
                            <dt class="text-base font-bold text-slate-900">Sisa Biaya Perpanjangan</dt>
                            <dd id="preview_sisa_biaya" class="tabular-nums text-lg font-bold text-slate-900">Rp 0</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <label for="bukti_bayar" class="block text-sm font-medium text-slate-700">
                        Bukti Bayar Perpanjangan (JPG, JPEG, PNG, WEBP — Maks. 5 MB)
                    </label>
                    <input id="bukti_bayar"
                           name="bukti_bayar"
                           type="file"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           required
                           class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800">
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Simpan Perpanjangan
                    </button>
                </div>
            </form>
        </section>

        {{-- Riwayat Pembayaran Sewa Ini --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Riwayat Pembayaran Sewa Ini</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Tipe</th>
                            <th class="px-6 py-3">Durasi</th>
                            <th class="px-6 py-3">Akhir Baru</th>
                            <th class="px-6 py-3 text-right">Nominal</th>
                            <th class="px-6 py-3">Dicatat Oleh</th>
                            <th class="px-6 py-3 text-right">Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rental->payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-3 font-medium capitalize text-slate-900">
                                    {{ $payment->payment_type === 'initial' ? 'Sewa Awal' : 'Perpanjangan' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-slate-700">
                                    +{{ $payment->added_days }} hari
                                    @if ($payment->added_months)
                                        ({{ $payment->added_months }} bulan)
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 font-mono text-slate-700">
                                    {{ $payment->new_end_date->toDateString() }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums font-semibold text-slate-900">
                                    Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-slate-600">
                                    {{ $payment->recorder?->nama_lengkap ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-right">
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($payment->proof_image_path) }}"
                                       target="_blank"
                                       class="text-xs font-semibold text-slate-900 underline underline-offset-4 hover:text-slate-700">
                                        Lihat Bukti
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const rentalType = @json($rental->rental_type);
            const oldEndDateIso = @json($rental->end_date->toDateString());
            const dailyRate = @json((float) $rental->garage->daily_rate);

            const previewEndEl = document.getElementById('preview_new_end_date');
            const previewDaysEl = document.getElementById('preview_added_days');
            const previewCostEl = document.getElementById('preview_sisa_biaya');

            function formatIdr(amount) {
                const rounded = Math.round(amount * 100) / 100;
                const hasDecimals = Math.abs(rounded - Math.trunc(rounded)) > 0.001;
                return 'Rp ' + rounded.toLocaleString('id-ID', {
                    minimumFractionDigits: hasDecimals ? 2 : 0,
                    maximumFractionDigits: hasDecimals ? 2 : 0
                });
            }

            function parseUtcDate(iso) {
                const [y, m, d] = iso.split('-').map(Number);
                return new Date(Date.UTC(y, m - 1, d));
            }

            function formatUtcIso(date) {
                const y = date.getUTCFullYear();
                const m = String(date.getUTCMonth() + 1).padStart(2, '0');
                const d = String(date.getUTCDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            function daysInMonthUtc(year, monthIndexZeroBased) {
                return new Date(Date.UTC(year, monthIndexZeroBased + 1, 0)).getUTCDate();
            }

            if (rentalType === 'harian') {
                const dateInput = document.getElementById('new_end_date');
                const oldUtc = parseUtcDate(oldEndDateIso);

                function updateHarianPreview() {
                    if (!dateInput.value) {
                        previewEndEl.textContent = '—';
                        previewDaysEl.textContent = '0 hari';
                        previewCostEl.textContent = 'Rp 0';
                        return;
                    }

                    const newUtc = parseUtcDate(dateInput.value);
                    const diffDays = Math.round((newUtc.getTime() - oldUtc.getTime()) / 86400000);

                    if (diffDays <= 0) {
                        previewEndEl.textContent = 'Tidak valid (harus > ' + oldEndDateIso + ')';
                        previewDaysEl.textContent = '0 hari';
                        previewCostEl.textContent = 'Rp 0';
                        return;
                    }

                    previewEndEl.textContent = dateInput.value;
                    previewDaysEl.textContent = `${diffDays} hari`;
                    previewCostEl.textContent = formatIdr(diffDays * dailyRate);
                }

                dateInput.addEventListener('change', updateHarianPreview);
                dateInput.addEventListener('input', updateHarianPreview);
                updateHarianPreview();
            } else {
                const monthsInput = document.getElementById('added_months');
                const oldUtc = parseUtcDate(oldEndDateIso);

                function updateBulananPreview() {
                    const addedMonths = parseInt(monthsInput.value || '0', 10);

                    if (!addedMonths || addedMonths < 1) {
                        previewEndEl.textContent = '—';
                        previewDaysEl.textContent = '0 hari';
                        previewCostEl.textContent = 'Rp 0';
                        return;
                    }

                    const cursor = new Date(oldUtc.getTime());

                    for (let i = 0; i < addedMonths; i++) {
                        const cycleStart = new Date(cursor.getTime() + 86400000);
                        const days = daysInMonthUtc(cycleStart.getUTCFullYear(), cycleStart.getUTCMonth());
                        cursor.setUTCDate(cursor.getUTCDate() + days);
                    }

                    const diffDays = Math.round((cursor.getTime() - oldUtc.getTime()) / 86400000);
                    const cost = addedMonths * 25 * dailyRate;

                    previewEndEl.textContent = formatUtcIso(cursor);
                    previewDaysEl.textContent = `${diffDays} hari (${addedMonths} bulan)`;
                    previewCostEl.textContent = formatIdr(cost);
                }

                monthsInput.addEventListener('input', updateBulananPreview);
                monthsInput.addEventListener('change', updateBulananPreview);
                updateBulananPreview();
            }
        })();
    </script>
@endpush
