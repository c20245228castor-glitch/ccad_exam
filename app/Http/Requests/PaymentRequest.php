<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
                'gt:0',
                'decimal:0,2',
                'max:99999999.99',
            ],
            'payment_method' => [
                'nullable',
                'string',
                Rule::in(array_keys(Payment::METHODS)),
            ],
            'payment_date' => [
                'required',
                'date_format:Y-m-d\TH:i',
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
            'amount_paid.gt' => 'The payment amount must be greater than 0.',
            'amount_paid.decimal' => 'The payment amount can have at most 2 decimal places.',
            'amount_paid.max' => 'The payment amount is too large.',
            'payment_method.in' => 'Select a supported payment method.',
            'payment_date.required' => 'The payment date is required.',
            'payment_date.date_format' => 'Enter a valid payment date and time.',
        ];
    }
}
