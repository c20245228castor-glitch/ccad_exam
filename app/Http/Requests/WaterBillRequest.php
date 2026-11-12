<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WaterBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'previous_reading' => is_string($this->input('previous_reading'))
                ? trim($this->input('previous_reading'))
                : $this->input('previous_reading'),
            'current_reading' => is_string($this->input('current_reading'))
                ? trim($this->input('current_reading'))
                : $this->input('current_reading'),
            'billing_date' => is_string($this->input('billing_date'))
                ? trim($this->input('billing_date'))
                : $this->input('billing_date'),
            'due_date' => is_string($this->input('due_date'))
                ? trim($this->input('due_date'))
                : $this->input('due_date'),
        ]);
    }

    public function rules(): array
    {
        return [
            'meter_id' => ['required', 'integer', 'exists:water_meters,meter_id'],
            'rate_id' => ['required', 'integer', 'exists:water_rates,rate_id'],
            'previous_reading' => ['required', 'numeric', 'min:0'],
            'current_reading' => ['required', 'numeric', 'gte:previous_reading'],
            'billing_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:billing_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'meter_id.required' => 'Please select a water meter.',
            'meter_id.exists' => 'The selected water meter does not exist.',
            'rate_id.required' => 'Please select a water rate.',
            'rate_id.exists' => 'The selected water rate does not exist.',
            'previous_reading.required' => 'The previous reading is required.',
            'previous_reading.numeric' => 'The previous reading must be a number.',
            'previous_reading.min' => 'The previous reading cannot be negative.',
            'current_reading.required' => 'The current reading is required.',
            'current_reading.numeric' => 'The current reading must be a number.',
            'current_reading.gte' => 'The current reading cannot be less than the previous reading.',
            'billing_date.required' => 'The billing date is required.',
            'billing_date.date' => 'Please enter a valid billing date.',
            'due_date.required' => 'The due date is required.',
            'due_date.date' => 'Please enter a valid due date.',
            'due_date.after_or_equal' => 'The due date must be on or after the billing date.',
        ];
    }
}
