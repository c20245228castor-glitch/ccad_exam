<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_reference' => trim((string) $this->input('payment_reference', '')),
            'amount_paid' => trim((string) $this->input('amount_paid', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'bill_id' => [
                'required',
                'integer',
                'exists:water_bills,bill_id',
            ],
            'amount_paid' => [
                'required',
                'numeric',
                'min:0',
            ],
            'payment_date' => [
                'required',
                'date',
            ],
            'payment_reference' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payments', 'payment_reference'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bill_id.required' => 'The bill reference is required.',
            'bill_id.integer' => 'The bill reference must be a valid number.',
            'bill_id.exists' => 'The selected bill does not exist.',
            'amount_paid.required' => 'The payment amount is required.',
            'amount_paid.numeric' => 'The payment amount must be a valid number.',
            'amount_paid.min' => 'The payment amount cannot be less than 0.',
            'payment_date.required' => 'The payment date is required.',
            'payment_date.date' => 'The payment date must be a valid date.',
            'payment_reference.required' => 'The payment reference is required.',
            'payment_reference.string' => 'The payment reference must be a valid string.',
            'payment_reference.max' => 'The payment reference may not exceed 255 characters.',
            'payment_reference.unique' => 'This payment reference already exists.',
        ];
    }
}