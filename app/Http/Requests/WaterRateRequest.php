<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WaterRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rate_per_cu_m' => [
                'required',
                'numeric',
                'min:0',
            ],
            'effective_from' => [
                'required',
                'date',
            ],
            'effective_to' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rate_per_cu_m.required' => 'The water rate is required.',
            'rate_per_cu_m.numeric' => 'The water rate must be a valid number.',
            'rate_per_cu_m.min' => 'The water rate cannot be less than 0.',
            'effective_from.required' => 'The effective from date is required.',
            'effective_from.date' => 'The effective from date must be a valid date.',
            'effective_to.date' => 'The effective to date must be a valid date.',
        ];
    }
}
