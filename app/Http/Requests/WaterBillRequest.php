<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WaterBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Sanitization
    protected function prepareForValidation(): void
    {
        $this->merge([
            'previous_reading' => trim(
                (string) $this->input('previous_reading', '')
            ),
            'current_reading' => trim(
                (string) $this->input('current_reading', '')
            ),
        ]);
    }

    // Validation
    public function rules(): array
    {
        return [
            'meter_id' => [
                'required',
                'exists:water_meters,meter_id',
            ],
            'rate_id' => [
                'required',
                'exists:water_rates,rate_id',
            ],
            'previous_reading' => [
                'required',
                'numeric',
                'min:0',
            ],
            'current_reading' => [
                'required',
                'numeric',
                'gte:previous_reading',
            ],
            'billing_date' => [
                'required',
                'date',
            ],
            'due_date' => [
                'required',
                'date',
                'after_or_equal:billing_date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'meter_id.required' => 'The meter is required.',
            'meter_id.exists' => 'The selected meter does not exist.',
            'rate_id.required' => 'The water rate is required.',
            'rate_id.exists' => 'The selected water rate does not exist.',
            'previous_reading.required' => 'The previous reading is required.',
            'previous_reading.numeric' => 'The previous reading must be a valid number.',
            'previous_reading.min' => 'The previous reading cannot be less than 0.',
            'current_reading.required' => 'The current reading is required.',
            'current_reading.numeric' => 'The current reading must be a valid number.',
            'current_reading.gte' => 'The current reading cannot be less than the previous reading.',
            'billing_date.required' => 'The billing date is required.',
            'billing_date.date' => 'The billing date must be a valid date.',
            'due_date.required' => 'The due date is required.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date must be on or after the billing date.',
        ];
    }
}