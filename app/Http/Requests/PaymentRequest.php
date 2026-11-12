<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('payment_reference'))) {
            $this->merge([
                'payment_reference' => trim($this->input('payment_reference')),
            ]);
        }

        if (is_string($this->input('amount_paid'))) {
            $this->merge([
                'amount_paid' => trim($this->input('amount_paid')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'bill_id' => ['required', 'integer', 'exists:water_bills,bill_id'],
            'amount_paid' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => [
                'required',
                'string',
                'max:255',
                'unique:payments,payment_reference',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bill_id.required' => 'Please select a bill.',
            'bill_id.exists' => 'The selected bill does not exist.',
            'amount_paid.required' => 'The payment amount is required.',
            'amount_paid.numeric' => 'The payment amount must be a valid number.',
            'amount_paid.gt' => 'The payment amount must be greater than zero.',
            'payment_date.required' => 'The payment date is required.',
            'payment_date.date' => 'Please enter a valid payment date.',
            'payment_date.before_or_equal' => 'A payment date cannot be in the future.',
            'payment_reference.required' => 'The payment reference is required.',
            'payment_reference.unique' => 'This payment reference has already been used.',
        ];
    }
}
