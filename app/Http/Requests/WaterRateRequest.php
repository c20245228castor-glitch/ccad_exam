<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WaterRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('rate_per_cu_m'))) {
            $this->merge([
                'rate_per_cu_m' => trim($this->input('rate_per_cu_m')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'rate_per_cu_m' => ['required', 'numeric', 'min:0.01'],
            'effective_from' => ['required', 'date'],
            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rate_per_cu_m.required' => 'The water rate is required.',
            'rate_per_cu_m.numeric' => 'The water rate must be a valid number.',
            'rate_per_cu_m.min' => 'The water rate must be greater than zero.',
            'effective_from.required' => 'The starting date is required.',
            'effective_from.date' => 'Please enter a valid starting date.',
            'effective_to.date' => 'Please enter a valid ending date.',
            'effective_to.after_or_equal' => 'The ending date must be on or after the starting date.',
        ];
    }
}
