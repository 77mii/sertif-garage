@extends('layouts.app')

@section('title', 'Sewa Baru — Garasi Kendaraan')

@section('content')
    @php
        $garageTypeLabels = [
            'sepeda' => 'Garasi Sepeda',
            'mobil' => 'Garasi Mobil',
            'mobil_listrik' => 'Garasi Mobil Listrik',
        ];
        $vehicleTypeLabels = [
            'sepeda' => 'Sepeda',
            'sepeda_motor' => 'Sepeda Motor',
            'mobil' => 'Mobil',
            'mobil_listrik' => 'Mobil Listrik',
        ];
        $confirmation = session('rental_confirmation');
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Sewa Baru</h1>
            <p class="mt-1 text-sm text-slate-600">
                Isi detail kendaraan, pilih periode dan garasi yang tersedia hari ini ({{ $today->format('d M Y') }} WIB), lalu unggah bukti pelunasan biaya.
            </p>
        </div>

        @if ($confirmation)
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-6 shadow-xs">
                <h2 class="text-base font-bold text-emerald-950">Konfirmasi Sewa Garasi</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-emerald-800">Kode Garasi</dt>
                        <dd class="font-mono text-base font-bold text-emerald-950">{{ $confirmation['garage_code'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-800">Kendaraan</dt>
                        <dd class="font-semibold text-emerald-950">
                            {{ $confirmation['vehicle_brand_type'] }}
                            @if ($confirmation['license_plate'])
                                ({{ $confirmation['license_plate'] }})
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-800">Periode Sewa</dt>
                        <dd class="font-semibold text-emerald-950">
                            {{ $confirmation['start_date'] }} s/d {{ $confirmation['end_date'] }} ({{ $confirmation['duration_days'] }} hari)
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-800">Tipe Sewa</dt>
                        <dd class="font-semibold capitalize text-emerald-950">{{ $confirmation['rental_type'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-800">Biaya Pengisian Listrik</dt>
                        <dd class="tabular-nums font-semibold text-emerald-950">
                            Rp {{ number_format((float) $confirmation['ev_charging_fee'], 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-800">Total Biaya Sewa</dt>
                        <dd class="tabular-nums text-base font-bold text-emerald-950">
                            Rp {{ number_format((float) $confirmation['total_cost'], 0, ',', '.') }}
                        </dd>
                    </div>
                </dl>
            </div>
        @endif

        <form method="POST"
              action="{{ route('tenant.rentals.store') }}"
              enctype="multipart/form-data"
              id="sewa-baru-form"
              class="space-y-6">
            @csrf

            {{-- 1. Detail Kendaraan --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <h2 class="text-base font-semibold text-slate-900">1. Detail Kendaraan</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="vehicle_type" class="block text-sm font-medium text-slate-700">Tipe Kendaraan</label>
                        <select id="vehicle_type"
                                name="vehicle_type"
                                required
                                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                            <option value="">-- Pilih Tipe Kendaraan --</option>
                            @foreach ($vehicleTypeLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('vehicle_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="vehicle_brand_type" class="block text-sm font-medium text-slate-700">Merek dan Tipe Kendaraan</label>
                        <input id="vehicle_brand_type"
                               name="vehicle_brand_type"
                               type="text"
                               value="{{ old('vehicle_brand_type') }}"
                               required
                               placeholder="Contoh: Toyota Avanza / Polygon Strattos"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                    </div>

                    <div id="license_plate_wrapper">
                        <label for="license_plate" class="block text-sm font-medium text-slate-700">Nomor Polisi</label>
                        <input id="license_plate"
                               name="license_plate"
                               type="text"
                               value="{{ old('license_plate') }}"
                               placeholder="Contoh: B 1234 ABC"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                    </div>

                    <div id="battery_capacity_wrapper" class="hidden">
                        <label for="battery_capacity_kwh" class="block text-sm font-medium text-slate-700">Kapasitas Baterai (kWh)</label>
                        <input id="battery_capacity_kwh"
                               name="battery_capacity_kwh"
                               type="number"
                               step="0.01"
                               min="0.01"
                               value="{{ old('battery_capacity_kwh') }}"
                               placeholder="Contoh: 60.5"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">Biaya pengisian 0% → 100% hanya dikenakan jika memilih Garasi Mobil Listrik.</p>
                    </div>
                </div>
            </section>

            {{-- 2. Periode & Tipe Sewa --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <h2 class="text-base font-semibold text-slate-900">2. Periode &amp; Tipe Sewa</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="rental_type" class="block text-sm font-medium text-slate-700">Tipe Sewa</label>
                        <select id="rental_type"
                                name="rental_type"
                                required
                                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                            <option value="harian" @selected(old('rental_type', 'harian') === 'harian')>Harian</option>
                            <option value="bulanan" @selected(old('rental_type') === 'bulanan')>Bulanan (1 Bulan — {{ $daysInMonth }} Hari)</option>
                        </select>
                    </div>

                    <div id="jumlah_hari_wrapper">
                        <label for="jumlah_hari" class="block text-sm font-medium text-slate-700">
                            Jumlah Hari (1 – {{ $maxDailyDays }} hari)
                        </label>
                        <input id="jumlah_hari"
                               name="jumlah_hari"
                               type="number"
                               min="1"
                               max="{{ $maxDailyDays }}"
                               step="1"
                               value="{{ old('jumlah_hari', 1) }}"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                    </div>

                    <div>
                        <label for="start_date_display" class="block text-sm font-medium text-slate-700">Tanggal Mulai Sewa</label>
                        <input id="start_date_display"
                               type="text"
                               value="{{ $today->toDateString() }}"
                               readonly
                               class="mt-1 block w-full rounded-lg border border-slate-200 bg-slate-100 px-3.5 py-2.5 font-mono text-sm text-slate-700">
                    </div>

                    <div>
                        <label for="end_date_display" class="block text-sm font-medium text-slate-700">Tanggal Selesai Sewa (Inklusif)</label>
                        <input id="end_date_display"
                               type="text"
                               readonly
                               value=""
                               class="mt-1 block w-full rounded-lg border border-slate-200 bg-slate-100 px-3.5 py-2.5 font-mono text-sm font-semibold text-slate-900">
                    </div>
                </div>
            </section>

            {{-- 3. Pilihan Garasi --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <h2 class="text-base font-semibold text-slate-900">3. Pilih Tipe &amp; Garasi Tersedia</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="garage_type" class="block text-sm font-medium text-slate-700">Tipe Garasi</label>
                        <select id="garage_type"
                                name="garage_type"
                                required
                                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                            <option value="">-- Pilih Tipe Kendaraan Terlebih Dahulu --</option>
                        </select>
                    </div>

                    <div>
                        <label for="garage_id" class="block text-sm font-medium text-slate-700">Daftar Garasi Tersedia Hari Ini</label>
                        <select id="garage_id"
                                name="garage_id"
                                required
                                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                            <option value="">-- Pilih Tipe Garasi Terlebih Dahulu --</option>
                        </select>
                        <p id="garage_availability_hint" class="mt-1 text-xs text-slate-500"></p>
                    </div>
                </div>
            </section>

            {{-- 4. Ringkasan Biaya & Bukti Pelunasan --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <h2 class="text-base font-semibold text-slate-900">4. Total Biaya Sewa &amp; Bukti Pelunasan</h2>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600" id="base_cost_label">Biaya Dasar Sewa</dt>
                            <dd class="tabular-nums font-medium text-slate-900" id="base_cost_display">Rp 0</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600" id="ev_fee_label">Biaya Pengisian Listrik (0% → 100%)</dt>
                            <dd class="tabular-nums font-medium text-slate-900" id="ev_fee_display">Rp 0</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-200 pt-2.5">
                            <dt class="text-base font-bold text-slate-900">Total Biaya Sewa</dt>
                            <dd class="tabular-nums text-lg font-bold text-slate-900" id="total_cost_display">Rp 0</dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-5">
                    <label for="bukti_bayar" class="block text-sm font-medium text-slate-700">
                        Bukti Pelunasan Biaya (JPG, JPEG, PNG, WEBP — Maks. 5 MB)
                    </label>
                    <input id="bukti_bayar"
                           name="bukti_bayar"
                           type="file"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           required
                           class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800">
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Konfirmasi &amp; Kirim Sewa Baru
                    </button>
                </div>
            </section>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const config = {
                today: @json($today->toDateString()),
                daysInMonth: @json($daysInMonth),
                maxDailyDays: @json($maxDailyDays),
                garages: @json($garagePayload),
                compatibility: @json($compatibilityMatrix),
                oldGarageType: @json(old('garage_type')),
                oldGarageId: @json(old('garage_id')),
                garageTypeLabels: {
                    sepeda: 'Garasi Sepeda',
                    mobil: 'Garasi Mobil',
                    mobil_listrik: 'Garasi Mobil Listrik'
                }
            };

            const vehicleTypeEl = document.getElementById('vehicle_type');
            const licensePlateWrapper = document.getElementById('license_plate_wrapper');
            const licensePlateInput = document.getElementById('license_plate');
            const batteryWrapper = document.getElementById('battery_capacity_wrapper');
            const batteryInput = document.getElementById('battery_capacity_kwh');
            const rentalTypeEl = document.getElementById('rental_type');
            const jumlahHariWrapper = document.getElementById('jumlah_hari_wrapper');
            const jumlahHariInput = document.getElementById('jumlah_hari');
            const endDateDisplay = document.getElementById('end_date_display');
            const garageTypeEl = document.getElementById('garage_type');
            const garageIdEl = document.getElementById('garage_id');
            const availabilityHint = document.getElementById('garage_availability_hint');
            const baseCostLabel = document.getElementById('base_cost_label');
            const baseCostDisplay = document.getElementById('base_cost_display');
            const evFeeLabel = document.getElementById('ev_fee_label');
            const evFeeDisplay = document.getElementById('ev_fee_display');
            const totalCostDisplay = document.getElementById('total_cost_display');

            function formatIdr(amount) {
                const rounded = Math.round(amount * 100) / 100;
                const hasDecimals = Math.abs(rounded - Math.trunc(rounded)) > 0.001;
                return 'Rp ' + rounded.toLocaleString('id-ID', {
                    minimumFractionDigits: hasDecimals ? 2 : 0,
                    maximumFractionDigits: hasDecimals ? 2 : 0
                });
            }

            function addDaysToIso(isoDate, offsetDays) {
                const [y, m, d] = isoDate.split('-').map(Number);
                const utc = new Date(Date.UTC(y, m - 1, d + offsetDays));
                const yy = utc.getUTCFullYear();
                const mm = String(utc.getUTCMonth() + 1).padStart(2, '0');
                const dd = String(utc.getUTCDate()).padStart(2, '0');
                return `${yy}-${mm}-${dd}`;
            }

            function updateVehicleFields(preserveSelection) {
                const vt = vehicleTypeEl.value;

                if (vt === 'sepeda') {
                    licensePlateWrapper.classList.add('hidden');
                    licensePlateInput.disabled = true;
                    licensePlateInput.required = false;
                } else {
                    licensePlateWrapper.classList.remove('hidden');
                    licensePlateInput.disabled = false;
                    licensePlateInput.required = vt !== '';
                }

                if (vt === 'mobil_listrik') {
                    batteryWrapper.classList.remove('hidden');
                    batteryInput.disabled = false;
                    batteryInput.required = true;
                } else {
                    batteryWrapper.classList.add('hidden');
                    batteryInput.disabled = true;
                    batteryInput.required = false;
                }

                const allowedTypes = config.compatibility[vt] || [];
                const previousType = preserveSelection ? (garageTypeEl.value || config.oldGarageType) : garageTypeEl.value;
                garageTypeEl.innerHTML = '';

                if (allowedTypes.length === 0) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = '-- Pilih Tipe Kendaraan Terlebih Dahulu --';
                    garageTypeEl.appendChild(opt);
                } else {
                    allowedTypes.forEach((type) => {
                        const opt = document.createElement('option');
                        opt.value = type;
                        opt.textContent = config.garageTypeLabels[type] || type;
                        if (type === previousType) {
                            opt.selected = true;
                        }
                        garageTypeEl.appendChild(opt);
                    });
                }

                updateAvailableGarages(preserveSelection);
            }

            function updateAvailableGarages(preserveSelection) {
                const selectedType = garageTypeEl.value;
                const previousGarageId = preserveSelection ? String(garageIdEl.value || config.oldGarageId || '') : String(garageIdEl.value || '');
                garageIdEl.innerHTML = '';

                if (!selectedType) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = '-- Pilih Tipe Garasi Terlebih Dahulu --';
                    garageIdEl.appendChild(opt);
                    availabilityHint.textContent = '';
                    updateQuote();
                    return;
                }

                const matching = config.garages.filter((g) => g.type === selectedType);

                if (matching.length === 0) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'Tidak ada garasi tersedia untuk tipe ini hari ini';
                    garageIdEl.appendChild(opt);
                    availabilityHint.textContent = '0 unit tersedia hari ini.';
                } else {
                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = '-- Pilih Garasi Tersedia --';
                    garageIdEl.appendChild(placeholder);

                    matching.forEach((g) => {
                        const opt = document.createElement('option');
                        opt.value = String(g.id);
                        let label = `${g.code} — ${formatIdr(g.daily_rate)}/hari`;
                        if (g.type === 'mobil_listrik' && g.kwh_rate !== null) {
                            label += ` | ${formatIdr(g.kwh_rate)}/kWh`;
                        }
                        opt.textContent = label;
                        if (String(g.id) === previousGarageId) {
                            opt.selected = true;
                        }
                        garageIdEl.appendChild(opt);
                    });

                    availabilityHint.textContent = `${matching.length} unit tersedia hari ini.`;
                }

                updateQuote();
            }

            function updatePeriodFields() {
                const isBulanan = rentalTypeEl.value === 'bulanan';

                if (isBulanan) {
                    jumlahHariWrapper.classList.add('hidden');
                    jumlahHariInput.disabled = true;
                    jumlahHariInput.required = false;
                    endDateDisplay.value = addDaysToIso(config.today, config.daysInMonth - 1);
                } else {
                    jumlahHariWrapper.classList.remove('hidden');
                    jumlahHariInput.disabled = false;
                    jumlahHariInput.required = true;
                    const days = Math.max(1, Math.min(config.maxDailyDays, parseInt(jumlahHariInput.value || '1', 10) || 1));
                    endDateDisplay.value = addDaysToIso(config.today, days - 1);
                }

                updateQuote();
            }

            function updateQuote() {
                const selectedGarage = config.garages.find((g) => String(g.id) === String(garageIdEl.value));

                if (!selectedGarage) {
                    baseCostLabel.textContent = 'Biaya Dasar Sewa';
                    baseCostDisplay.textContent = 'Rp 0';
                    evFeeLabel.textContent = 'Biaya Pengisian Listrik (0% → 100%)';
                    evFeeDisplay.textContent = 'Rp 0';
                    totalCostDisplay.textContent = 'Rp 0';
                    return;
                }

                const isBulanan = rentalTypeEl.value === 'bulanan';
                let baseCost = 0;

                if (isBulanan) {
                    baseCost = 25 * selectedGarage.daily_rate;
                    baseCostLabel.textContent = `Biaya Dasar Sewa (Bulanan: 25 × ${formatIdr(selectedGarage.daily_rate)})`;
                } else {
                    const days = Math.max(1, Math.min(config.maxDailyDays, parseInt(jumlahHariInput.value || '1', 10) || 1));
                    baseCost = days * selectedGarage.daily_rate;
                    baseCostLabel.textContent = `Biaya Dasar Sewa (Harian: ${days} × ${formatIdr(selectedGarage.daily_rate)})`;
                }

                let evFee = 0;
                if (selectedGarage.type === 'mobil_listrik' && vehicleTypeEl.value === 'mobil_listrik') {
                    const kwh = parseFloat(batteryInput.value || '0') || 0;
                    const kwhRate = selectedGarage.kwh_rate || 0;
                    evFee = Math.round(kwh * kwhRate * 100) / 100;
                    evFeeLabel.textContent = `Biaya Pengisian Listrik (${kwh} kWh × ${formatIdr(kwhRate)})`;
                } else {
                    evFeeLabel.textContent = 'Biaya Pengisian Listrik (Tidak dikenakan)';
                }

                const total = Math.round((baseCost + evFee) * 100) / 100;

                baseCostDisplay.textContent = formatIdr(baseCost);
                evFeeDisplay.textContent = formatIdr(evFee);
                totalCostDisplay.textContent = formatIdr(total);
            }

            vehicleTypeEl.addEventListener('change', () => updateVehicleFields(false));
            garageTypeEl.addEventListener('change', () => updateAvailableGarages(false));
            garageIdEl.addEventListener('change', updateQuote);
            rentalTypeEl.addEventListener('change', updatePeriodFields);
            jumlahHariInput.addEventListener('input', updatePeriodFields);
            batteryInput.addEventListener('input', updateQuote);

            updateVehicleFields(true);
            updatePeriodFields();
        })();
    </script>
@endpush
