<?php

namespace App\Http\Requests;

use App\Models\Garage;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isPenyewa() && $user->isApproved();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = Carbon::today('Asia/Jakarta');
        $todayString = $today->toDateString();
        $endOfMonthString = $today->copy()->endOfMonth()->toDateString();
        $maxDailyDays = $today->daysInMonth - 1;
        $rentalType = (string) $this->input('rental_type');

        $startDateRules = $rentalType === Rental::TYPE_HARIAN
            ? ['required', 'date', "after_or_equal:{$todayString}", "before_or_equal:{$endOfMonthString}"]
            : ['nullable', 'date', "date_equals:{$todayString}"];

        return [
            'vehicle_type' => ['required', 'string', Rule::in(Rental::VEHICLE_TYPES)],
            'license_plate' => [
                Rule::requiredIf(fn (): bool => $this->input('vehicle_type') !== Rental::VEHICLE_SEPEDA),
                'nullable',
                'string',
                'max:30',
            ],
            'vehicle_brand_type' => ['required', 'string', 'max:255'],
            'battery_capacity_kwh' => [
                Rule::requiredIf(fn (): bool => $this->input('vehicle_type') === Rental::VEHICLE_MOBIL_LISTRIK),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'rental_type' => ['required', 'string', Rule::in(Rental::RENTAL_TYPES)],
            'start_date' => $startDateRules,
            'jumlah_hari' => [
                Rule::requiredIf(fn (): bool => $this->input('rental_type') === Rental::TYPE_HARIAN),
                'nullable',
                'integer',
                'min:1',
                "max:{$maxDailyDays}",
            ],
            'garage_type' => ['required', 'string', Rule::in(Garage::TYPES)],
            'garage_id' => ['required', 'integer', 'exists:garages,id'],
            'bukti_bayar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $garage = Garage::query()->find((int) $this->input('garage_id'));

            if ($garage !== null && $garage->type !== $this->input('garage_type')) {
                $validator->errors()->add('garage_id', 'Garasi yang dipilih tidak sesuai dengan tipe garasi yang dipilih.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $today = Carbon::today('Asia/Jakarta');
        $todayString = $today->toDateString();
        $endOfMonthString = $today->copy()->endOfMonth()->toDateString();
        $maxDailyDays = $today->daysInMonth - 1;

        return [
            'vehicle_type.required' => 'Tipe kendaraan wajib dipilih.',
            'license_plate.required' => 'Nomor polisi wajib diisi untuk tipe kendaraan ini.',
            'vehicle_brand_type.required' => 'Merek dan tipe kendaraan wajib diisi.',
            'battery_capacity_kwh.required' => 'Kapasitas baterai (kWh) wajib diisi untuk mobil listrik.',
            'battery_capacity_kwh.gt' => 'Kapasitas baterai harus lebih besar dari 0 kWh.',
            'rental_type.required' => 'Tipe sewa wajib dipilih.',
            'start_date.required' => 'Tanggal mulai sewa wajib diisi untuk sewa harian.',
            'start_date.date' => 'Format tanggal mulai sewa tidak valid.',
            'start_date.after_or_equal' => "Tanggal mulai sewa harian minimal hari ini ({$todayString}).",
            'start_date.before_or_equal' => "Tanggal mulai sewa harian maksimal akhir bulan ini ({$endOfMonthString}).",
            'start_date.date_equals' => "Tanggal mulai sewa bulanan harus hari ini ({$todayString}).",
            'jumlah_hari.required' => 'Jumlah hari wajib diisi untuk sewa harian.',
            'jumlah_hari.min' => 'Jumlah hari sewa harian minimal 1 hari.',
            'jumlah_hari.max' => "Jumlah hari sewa harian maksimal {$maxDailyDays} hari untuk bulan ini.",
            'garage_type.required' => 'Tipe garasi wajib dipilih.',
            'garage_id.required' => 'Garasi yang tersedia wajib dipilih.',
            'bukti_bayar.required' => 'Bukti pelunasan biaya wajib diunggah.',
            'bukti_bayar.image' => 'Bukti pelunasan biaya harus berupa gambar.',
            'bukti_bayar.mimes' => 'Format gambar bukti bayar harus jpg, jpeg, png, atau webp.',
            'bukti_bayar.max' => 'Ukuran gambar bukti bayar maksimal 5 MB.',
        ];
    }
}
