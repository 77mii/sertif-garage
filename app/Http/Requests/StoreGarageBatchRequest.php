<?php

namespace App\Http\Requests;

use App\Models\Garage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGarageBatchRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in(Garage::TYPES)],
            'quantity' => ['required', 'integer', 'min:1'],
            'daily_rate' => ['required', 'numeric', 'gt:0'],
            'kwh_rate' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === Garage::TYPE_MOBIL_LISTRIK),
                'nullable',
                'numeric',
                'gt:0',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Tipe garasi wajib dipilih.',
            'type.in' => 'Tipe garasi tidak valid.',
            'quantity.required' => 'Jumlah garasi wajib diisi.',
            'quantity.integer' => 'Jumlah garasi harus berupa bilangan bulat.',
            'quantity.min' => 'Jumlah garasi minimal 1 unit.',
            'daily_rate.required' => 'Tarif harian wajib diisi.',
            'daily_rate.numeric' => 'Tarif harian harus berupa angka.',
            'daily_rate.gt' => 'Tarif harian harus lebih besar dari 0.',
            'kwh_rate.required' => 'Tarif per kWh wajib diisi untuk Garasi Mobil Listrik.',
            'kwh_rate.numeric' => 'Tarif per kWh harus berupa angka.',
            'kwh_rate.gt' => 'Tarif per kWh harus lebih besar dari 0.',
        ];
    }
}
