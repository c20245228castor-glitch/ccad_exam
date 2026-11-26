<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsumerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount_paid' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:99999999.99',
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::in(array_keys(Payment::METHODS)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_paid.required' => 'Enter the amount you want to pay.',
            'amount_paid.numeric' => 'The payment amount must be a valid number.',
            'amount_paid.gt' => 'The payment amount must be greater than 0.',
            'amount_paid.decimal' => 'The payment amount can have at most 2 decimal places.',
            'amount_paid.max' => 'The payment amount is too large.',
            'payment_method.required' => 'Select a payment method.',
            'payment_method.in' => 'Select a supported payment method.',
        ];
    }
}
