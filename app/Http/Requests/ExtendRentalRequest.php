<?php

namespace App\Http\Requests;

use App\Models\Rental;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtendRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Rental|null $rental */
        $rental = $this->route('rental');

        $rules = [
            'bukti_bayar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        if ($rental !== null && $rental->rental_type === Rental::TYPE_HARIAN) {
            $rules['new_end_date'] = ['required', 'date', 'after:'.$rental->end_date->toDateString()];
        } elseif ($rental !== null && $rental->rental_type === Rental::TYPE_BULANAN) {
            $rules['added_months'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_end_date.required' => 'Tanggal selesai baru wajib diisi.',
            'new_end_date.date' => 'Format tanggal selesai baru tidak valid.',
            'new_end_date.after' => 'Tanggal selesai baru harus lebih besar dari tanggal selesai saat ini (tidak boleh diperpendek).',
            'added_months.required' => 'Jumlah bulan tambahan wajib diisi.',
            'added_months.integer' => 'Jumlah bulan tambahan harus berupa bilangan bulat.',
            'added_months.min' => 'Jumlah bulan tambahan minimal 1 bulan.',
            'bukti_bayar.required' => 'Bukti pembayaran perpanjangan wajib diunggah.',
            'bukti_bayar.image' => 'Bukti pembayaran harus berupa file gambar.',
            'bukti_bayar.mimes' => 'Format gambar bukti bayar harus jpg, jpeg, png, atau webp.',
            'bukti_bayar.max' => 'Ukuran gambar bukti bayar maksimal 5 MB.',
        ];
    }
}
