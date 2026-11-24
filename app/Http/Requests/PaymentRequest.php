<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
                'min:0',
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
            'amount_paid.min' => 'The payment amount cannot be less than 0.',
            'payment_date.required' => 'The payment date is required.',
            'payment_date.date_format' => 'Enter a valid payment date and time.',
        ];
    }
}
