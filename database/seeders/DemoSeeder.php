<?php

namespace Database\Seeders;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use App\Services\GarageAvailabilityService;
use App\Services\RentalExtensionService;
use App\Services\RentalPricingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    private const PROOF_IMAGE_PATH = 'payment_proofs/demo_bukti_bayar.png';

    /**
     * Populate realistic Indonesian demo data for certification screenshots.
     *
     * Run manually via:
     *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $today = Carbon::today('Asia/Jakarta');
        $now = Carbon::now('Asia/Jakarta');

        /** @var RentalPricingService $pricingService */
        $pricingService = app(RentalPricingService::class);
        /** @var RentalExtensionService $extensionService */
        $extensionService = app(RentalExtensionService::class);
        /** @var GarageAvailabilityService $availabilityService */
        $availabilityService = app(GarageAvailabilityService::class);

        // 1. Ensure Admin & Valid Demo Proof Image Exist
        $this->call(AdminSeeder::class);
        $admin = User::query()->where('email', 'admin@garasi.test')->firstOrFail();

        $this->ensureDemoProofImage();
        $this->ensureStorageSymlink();

        // 2. Seed Tenants (Penyewa) — All Using Password 'password'
        $tenants = $this->seedTenants($now);
        $budi = $tenants['budi@garasi.test'];
        $siti = $tenants['siti@garasi.test'];
        $andi = $tenants['andi@garasi.test'];

        // 3. Seed Garages (Per-Type Incremental Codes & Realistic IDR Rates)
        $garages = $this->seedGarages();

        // 4. Active Rentals Today (start_date <= today <= end_date)
        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $budi,
            garage: $garages['S001'],
            vehicleType: Rental::VEHICLE_SEPEDA_MOTOR,
            licensePlate: 'B 4821 KTR',
            vehicleBrandType: 'Honda Vario 160 ABS',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $today->copy()->subDays(2),
            jumlahHari: 5
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $budi,
            garage: $garages['C001'],
            vehicleType: Rental::VEHICLE_MOBIL,
            licensePlate: 'B 1945 GRS',
            vehicleBrandType: 'Toyota Kijang Innova Zenix',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $today->copy()->subDay(),
            jumlahHari: 7
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $siti,
            garage: $garages['E001'],
            vehicleType: Rental::VEHICLE_MOBIL_LISTRIK,
            licensePlate: 'B 2026 EVX',
            vehicleBrandType: 'Hyundai Ioniq 5 Prime Long Range',
            batteryCapacityKwh: '72.60',
            rentalType: Rental::TYPE_BULANAN,
            startDate: $today->copy()->subDays(5),
            jumlahHari: null
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $andi,
            garage: $garages['C002'],
            vehicleType: Rental::VEHICLE_MOBIL_LISTRIK,
            licensePlate: 'D 8812 EVW',
            vehicleBrandType: 'Wuling Air ev Long Range',
            batteryCapacityKwh: '26.70',
            rentalType: Rental::TYPE_HARIAN,
            startDate: $today->copy(),
            jumlahHari: 3
        );

        // 5. Completed Historical Rentals (end_date < today)
        // 5A. Earlier Months of Current Year (6 rentals across 5 earlier months, including 1 daily + 1 monthly extension)
        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $budi,
            garage: $garages['S002'],
            vehicleType: Rental::VEHICLE_SEPEDA,
            licensePlate: null,
            vehicleBrandType: 'Polygon Strattos S5 Disc',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->earlierMonthStartDate($today, 5, 5),
            jumlahHari: 5,
            extension: ['added_days' => 4]
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $andi,
            garage: $garages['C003'],
            vehicleType: Rental::VEHICLE_MOBIL,
            licensePlate: 'B 7710 KLM',
            vehicleBrandType: 'Honda Civic RS Turbo',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_BULANAN,
            startDate: $this->earlierMonthStartDate($today, 4, 3),
            jumlahHari: null
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $siti,
            garage: $garages['E002'],
            vehicleType: Rental::VEHICLE_MOBIL_LISTRIK,
            licensePlate: 'B 8890 EVK',
            vehicleBrandType: 'BYD Seal Performance AWD',
            batteryCapacityKwh: '82.56',
            rentalType: Rental::TYPE_BULANAN,
            startDate: $this->earlierMonthStartDate($today, 3, 5),
            jumlahHari: null,
            extension: ['added_months' => 1]
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $budi,
            garage: $garages['C004'],
            vehicleType: Rental::VEHICLE_MOBIL,
            licensePlate: 'B 3321 JKT',
            vehicleBrandType: 'Mitsubishi Xpander Cross',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->earlierMonthStartDate($today, 2, 10),
            jumlahHari: 6
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $andi,
            garage: $garages['E003'],
            vehicleType: Rental::VEHICLE_MOBIL_LISTRIK,
            licensePlate: 'D 5541 EVN',
            vehicleBrandType: 'Chery Omoda E5',
            batteryCapacityKwh: '61.00',
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->earlierMonthStartDate($today, 2, 18),
            jumlahHari: 4
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $siti,
            garage: $garages['S003'],
            vehicleType: Rental::VEHICLE_SEPEDA_MOTOR,
            licensePlate: 'B 6190 NMX',
            vehicleBrandType: 'Yamaha NMAX Turbo Tech Max',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->earlierMonthStartDate($today, 1, 8),
            jumlahHari: 10
        );

        // 5B. Distinct Earlier Dates Inside the Current Month (4 completed rentals)
        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $andi,
            garage: $garages['S002'],
            vehicleType: Rental::VEHICLE_SEPEDA_MOTOR,
            licensePlate: 'B 5120 PCX',
            vehicleBrandType: 'Honda PCX 160 RoadSync',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->completedCurrentMonthStartDate($today, 2, 3, 20),
            jumlahHari: 3
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $budi,
            garage: $garages['C003'],
            vehicleType: Rental::VEHICLE_MOBIL,
            licensePlate: 'B 2104 HRV',
            vehicleBrandType: 'Honda HR-V SE CVT',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->completedCurrentMonthStartDate($today, 5, 4, 16),
            jumlahHari: 4
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $siti,
            garage: $garages['E003'],
            vehicleType: Rental::VEHICLE_MOBIL_LISTRIK,
            licensePlate: 'B 9012 EVB',
            vehicleBrandType: 'Hyundai Kona Electric Signature',
            batteryCapacityKwh: '66.00',
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->completedCurrentMonthStartDate($today, 9, 3, 12),
            jumlahHari: 3
        );

        $this->seedRental(
            pricingService: $pricingService,
            extensionService: $extensionService,
            admin: $admin,
            tenant: $andi,
            garage: $garages['C004'],
            vehicleType: Rental::VEHICLE_MOBIL,
            licensePlate: 'B 6732 ERT',
            vehicleBrandType: 'Suzuki All New Ertiga Hybrid',
            batteryCapacityKwh: null,
            rentalType: Rental::TYPE_HARIAN,
            startDate: $this->completedCurrentMonthStartDate($today, 12, 4, 8),
            jumlahHari: 4
        );

        // 6. Sync Garage Statuses accurately for today
        $activeGarageIds = Rental::query()
            ->activeOn($today)
            ->pluck('garage_id')
            ->unique()
            ->all();

        if ($activeGarageIds !== []) {
            Garage::query()
                ->whereIn('id', $activeGarageIds)
                ->update([
                    'status' => Garage::STATUS_USED,
                    'updated_at' => $now,
                ]);
        }

        $availabilityService->syncStatuses($today);
    }

    /**
     * Seed approved, pending, and rejected demo tenant accounts.
     *
     * @return array<string, User>
     */
    private function seedTenants(Carbon $now): array
    {
        $definitions = [
            [
                'email' => 'budi@garasi.test',
                'nik' => '3171041205880001',
                'nama_lengkap' => 'Budi Santoso',
                'nomor_telepon' => '081234567801',
                'verification_status' => User::STATUS_APPROVED,
                'verified_at' => $now->copy()->subMonths(6),
            ],
            [
                'email' => 'siti@garasi.test',
                'nik' => '3273085409910002',
                'nama_lengkap' => 'Siti Rahmawati',
                'nomor_telepon' => '081398765402',
                'verification_status' => User::STATUS_APPROVED,
                'verified_at' => $now->copy()->subMonths(5),
            ],
            [
                'email' => 'andi@garasi.test',
                'nik' => '3578122303940003',
                'nama_lengkap' => 'Andi Wijaya',
                'nomor_telepon' => '085712345603',
                'verification_status' => User::STATUS_APPROVED,
                'verified_at' => $now->copy()->subMonths(5),
            ],
            [
                'email' => 'pending1@garasi.test',
                'nik' => '3374066107960004',
                'nama_lengkap' => 'Dewi Lestari',
                'nomor_telepon' => '082145678904',
                'verification_status' => User::STATUS_PENDING,
                'verified_at' => null,
            ],
            [
                'email' => 'pending2@garasi.test',
                'nik' => '3671091811980005',
                'nama_lengkap' => 'Rizky Pratama',
                'nomor_telepon' => '087823456705',
                'verification_status' => User::STATUS_PENDING,
                'verified_at' => null,
            ],
            [
                'email' => 'rejected@garasi.test',
                'nik' => '3471030902900006',
                'nama_lengkap' => 'Hendra Kusuma',
                'nomor_telepon' => '081987654306',
                'verification_status' => User::STATUS_REJECTED,
                'verified_at' => null,
            ],
        ];

        $users = [];

        foreach ($definitions as $item) {
            $users[$item['email']] = User::query()->updateOrCreate(
                ['email' => $item['email']],
                [
                    'role' => User::ROLE_PENYEWA,
                    'nik' => $item['nik'],
                    'nama_lengkap' => $item['nama_lengkap'],
                    'nomor_telepon' => $item['nomor_telepon'],
                    'password' => 'password',
                    'verification_status' => $item['verification_status'],
                    'verified_at' => $item['verified_at'],
                ]
            );
        }

        return $users;
    }

    /**
     * Seed garages across all 3 types with per-type incremental codes.
     *
     * @return array<string, Garage>
     */
    private function seedGarages(): array
    {
        $specs = [
            [
                'prefix' => 'S',
                'count' => 5,
                'type' => Garage::TYPE_SEPEDA,
                'daily_rate' => '15000.00',
                'kwh_rate' => null,
            ],
            [
                'prefix' => 'C',
                'count' => 5,
                'type' => Garage::TYPE_MOBIL,
                'daily_rate' => '50000.00',
                'kwh_rate' => null,
            ],
            [
                'prefix' => 'E',
                'count' => 4,
                'type' => Garage::TYPE_MOBIL_LISTRIK,
                'daily_rate' => '75000.00',
                'kwh_rate' => '2500.00',
            ],
        ];

        $garages = [];

        foreach ($specs as $spec) {
            for ($i = 1; $i <= $spec['count']; $i++) {
                $code = sprintf('%s%03d', $spec['prefix'], $i);
                $garages[$code] = Garage::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'type' => $spec['type'],
                        'daily_rate' => $spec['daily_rate'],
                        'kwh_rate' => $spec['kwh_rate'],
                        'status' => Garage::STATUS_AVAILABLE,
                    ]
                );
            }
        }

        return $garages;
    }

    /**
     * Seed a single rental (and optional extension) using exact RentalPricingService
     * and RentalExtensionService math.
     *
     * @param  array{added_days?: int, added_months?: int}|null  $extension
     */
    private function seedRental(
        RentalPricingService $pricingService,
        RentalExtensionService $extensionService,
        User $admin,
        User $tenant,
        Garage $garage,
        string $vehicleType,
        ?string $licensePlate,
        string $vehicleBrandType,
        ?string $batteryCapacityKwh,
        string $rentalType,
        Carbon $startDate,
        ?int $jumlahHari,
        ?array $extension = null
    ): Rental {
        $quote = $pricingService->calculateInitialQuote(
            garage: $garage,
            vehicleType: $vehicleType,
            rentalType: $rentalType,
            jumlahHari: $jumlahHari,
            batteryCapacityKwh: $batteryCapacityKwh,
            startDate: $startDate
        );

        $existing = Rental::query()
            ->where('user_id', $tenant->id)
            ->where('garage_id', $garage->id)
            ->whereDate('start_date', $quote['start_date']->toDateString())
            ->where('vehicle_brand_type', $vehicleBrandType)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $initialTimestamp = $quote['start_date']->copy()->setTime(9, 30);

        $rental = new Rental([
            'user_id' => $tenant->id,
            'garage_id' => $garage->id,
            'vehicle_type' => $vehicleType,
            'license_plate' => $licensePlate,
            'vehicle_brand_type' => $vehicleBrandType,
            'battery_capacity_kwh' => $batteryCapacityKwh,
            'rental_type' => $rentalType,
            'start_date' => $quote['start_date']->toDateString(),
            'end_date' => $quote['end_date']->toDateString(),
            'initial_duration_days' => $quote['duration_days'],
            'ev_charging_fee' => $quote['ev_charging_fee'],
            'total_cost' => $quote['total_cost'],
        ]);
        $rental->created_at = $initialTimestamp;
        $rental->updated_at = $initialTimestamp;
        $rental->save();

        $initialPayment = new RentalPayment([
            'rental_id' => $rental->id,
            'payment_type' => RentalPayment::TYPE_INITIAL,
            'amount' => $quote['total_cost'],
            'added_days' => $quote['duration_days'],
            'added_months' => null,
            'previous_end_date' => null,
            'new_end_date' => $quote['end_date']->toDateString(),
            'proof_image_path' => self::PROOF_IMAGE_PATH,
            'recorded_by' => $tenant->id,
        ]);
        $initialPayment->created_at = $initialTimestamp;
        $initialPayment->updated_at = $initialTimestamp;
        $initialPayment->save();

        if ($extension !== null) {
            $rental->setRelation('garage', $garage);

            $extensionInput = isset($extension['added_months'])
                ? ['added_months' => $extension['added_months']]
                : ['new_end_date' => $quote['end_date']->copy()->addDays((int) $extension['added_days'])->toDateString()];

            $extQuote = $extensionService->calculateExtensionQuote(
                rental: $rental,
                data: $extensionInput,
                today: $quote['end_date']
            );

            $extTimestamp = $extQuote['previous_end_date']->copy()->setTime(14, 15);

            $extPayment = new RentalPayment([
                'rental_id' => $rental->id,
                'payment_type' => RentalPayment::TYPE_EXTENSION,
                'amount' => $extQuote['sisa_biaya'],
                'added_days' => $extQuote['added_days'],
                'added_months' => $extQuote['added_months'],
                'previous_end_date' => $extQuote['previous_end_date']->toDateString(),
                'new_end_date' => $extQuote['new_end_date']->toDateString(),
                'proof_image_path' => self::PROOF_IMAGE_PATH,
                'recorded_by' => $admin->id,
            ]);
            $extPayment->created_at = $extTimestamp;
            $extPayment->updated_at = $extTimestamp;
            $extPayment->save();

            $rental->end_date = $extQuote['new_end_date']->toDateString();
            $rental->total_cost = $pricingService->addMoney($rental->total_cost, $extQuote['sisa_biaya']);
            $rental->updated_at = $extTimestamp;
            $rental->save();
        }

        return $rental;
    }

    /**
     * Resolve a safe start date in an earlier month relative to today.
     */
    private function earlierMonthStartDate(Carbon $today, int $monthsAgo, int $dayOfMonth): Carbon
    {
        $monthStart = $today->copy()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
        $clampedDay = min($dayOfMonth, $monthStart->daysInMonth);

        return $monthStart->copy()->day($clampedDay);
    }

    /**
     * Resolve a start date inside the current month that finishes strictly before today,
     * with a safe fallback when seeded during the first days of a month.
     */
    private function completedCurrentMonthStartDate(
        Carbon $today,
        int $preferredDayOfMonth,
        int $durationDays,
        int $fallbackDaysBeforeToday
    ): Carbon {
        $candidate = $today->copy()->startOfMonth()->day($preferredDayOfMonth);
        $candidateEnd = $candidate->copy()->addDays($durationDays - 1);

        if ($candidateEnd->lt($today)) {
            return $candidate;
        }

        return $today->copy()->subDays(max($fallbackDaysBeforeToday, $durationDays));
    }

    /**
     * Ensure storage/app/public/payment_proofs/demo_bukti_bayar.png exists as a valid PNG image.
     */
    private function ensureDemoProofImage(): void
    {
        $pngBinary = function_exists('imagecreatetruecolor')
            ? $this->buildReceiptPngWithGd()
            : $this->buildReceiptPngPurePhp();

        Storage::disk('public')->put(self::PROOF_IMAGE_PATH, $pngBinary);
    }

    /**
     * Ensure public/storage symlink exists so receipt images render without broken icons.
     */
    private function ensureStorageSymlink(): void
    {
        $linkPath = public_path('storage');

        if (! file_exists($linkPath) && ! is_link($linkPath)) {
            Artisan::call('storage:link');
        }
    }

    /**
     * Build a crisp receipt placeholder PNG using GD when available.
     */
    private function buildReceiptPngWithGd(): string
    {
        $width = 480;
        $height = 300;
        $img = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocate($img, 241, 245, 249);
        $white = imagecolorallocate($img, 255, 255, 255);
        $border = imagecolorallocate($img, 203, 213, 225);
        $header = imagecolorallocate($img, 30, 41, 59);
        $accent = imagecolorallocate($img, 16, 185, 129);
        $textDark = imagecolorallocate($img, 30, 41, 59);
        $textMuted = imagecolorallocate($img, 100, 116, 139);

        imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $bg);
        imagefilledrectangle($img, 24, 20, $width - 25, $height - 21, $white);
        imagerectangle($img, 24, 20, $width - 25, $height - 21, $border);
        imagefilledrectangle($img, 24, 20, $width - 25, 76, $header);
        imagefilledrectangle($img, 340, 96, 432, 124, $accent);

        imagestring($img, 5, 44, 38, 'BUKTI PEMBAYARAN - DEMO', $white);
        imagestring($img, 4, 362, 102, 'LUNAS', $white);
        imagestring($img, 4, 44, 98, 'Manajemen Sewa Garasi', $textDark);
        imagestring($img, 3, 44, 132, 'Metode  : Transfer Bank (QRIS / VA)', $textMuted);
        imagestring($img, 3, 44, 156, 'Ref No  : TRX-DEMO-GARASI-2026', $textMuted);
        imagestring($img, 3, 44, 180, 'Status  : Terverifikasi Otomatis', $textMuted);
        imageline($img, 44, 212, $width - 45, 212, $border);
        imagestring($img, 3, 44, 230, 'Dokumen bukti transfer demo untuk', $textMuted);
        imagestring($img, 3, 44, 248, 'keperluan dokumentasi & pengujian.', $textMuted);

        ob_start();
        imagepng($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        return $binary;
    }

    /**
     * Build a valid 480x300 receipt card PNG in pure PHP (requires only zlib).
     */
    private function buildReceiptPngPurePhp(): string
    {
        $width = 480;
        $height = 300;

        $rawScanlines = '';

        for ($y = 0; $y < $height; $y++) {
            $row = "\x00"; // PNG filter type 0 (None)

            for ($x = 0; $x < $width; $x++) {
                [$r, $g, $b] = $this->receiptPixelColor($x, $y, $width, $height);
                $row .= chr($r).chr($g).chr($b);
            }

            $rawScanlines .= $row;
        }

        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $idatData = (string) gzcompress($rawScanlines, 6);

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', $ihdrData)
            .$this->pngChunk('IDAT', $idatData)
            .$this->pngChunk('IEND', '');
    }

    /**
     * Compute RGB pixel color for the pure-PHP receipt card graphic.
     *
     * @return array{int, int, int}
     */
    private function receiptPixelColor(int $x, int $y, int $width, int $height): array
    {
        // Outer slate background
        $cardLeft = 24;
        $cardRight = $width - 25;
        $cardTop = 20;
        $cardBottom = $height - 21;

        if ($x < $cardLeft || $x > $cardRight || $y < $cardTop || $y > $cardBottom) {
            return [241, 245, 249];
        }

        // Card border
        if ($x === $cardLeft || $x === $cardRight || $y === $cardTop || $y === $cardBottom) {
            return [203, 213, 225];
        }

        // Dark slate header bar
        if ($y <= 76) {
            if ($y >= 42 && $y <= 54 && $x >= 46 && $x <= 240) {
                return [248, 250, 252];
            }

            return [30, 41, 59];
        }

        // Emerald "paid" badge with checkmark on top-right
        if ($x >= 352 && $x <= 432 && $y >= 96 && $y <= 128) {
            $cx = $x - 352;
            $cy = $y - 96;

            $isCheckLeft = ($cx >= 24 && $cx <= 36) && abs(($cy - 16) - ($cx - 24) * 0.8) <= 2.2;
            $isCheckRight = ($cx >= 35 && $cx <= 56) && abs(($cy - 25) + ($cx - 35) * 0.75) <= 2.2;

            if ($isCheckLeft || $isCheckRight) {
                return [255, 255, 255];
            }

            return [16, 185, 129];
        }

        // Title bar & simulated receipt detail lines
        if ($y >= 100 && $y <= 112 && $x >= 46 && $x <= 230) {
            return [51, 65, 85];
        }

        if (($y >= 136 && $y <= 144 && $x >= 46 && $x <= 280)
            || ($y >= 160 && $y <= 168 && $x >= 46 && $x <= 245)
            || ($y >= 184 && $y <= 192 && $x >= 46 && $x <= 310)
        ) {
            return [148, 163, 184];
        }

        // Dashed divider line
        if ($y === 214 && $x >= 46 && $x <= $width - 47 && ((int) floor($x / 8) % 2 === 0)) {
            return [203, 213, 225];
        }

        // Footer total bar (left label + right emerald highlight)
        if ($y >= 234 && $y <= 246 && $x >= 46 && $x <= 155) {
            return [71, 85, 105];
        }

        if ($y >= 234 && $y <= 246 && $x >= 310 && $x <= 432) {
            return [16, 185, 129];
        }

        return [255, 255, 255];
    }

    private function pngChunk(string $type, string $data): string
    {
        $crc = crc32($type.$data);

        return pack('N', strlen($data)).$type.$data.pack('N', $crc);
    }
}
