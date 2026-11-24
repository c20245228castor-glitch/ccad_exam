<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WaterMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'consumer_id' => [
                'required',
                'exists:consumers,consumer_id',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'consumer_id.required' => 'The consumer is required.',
            'consumer_id.exists' => 'The selected consumer does not exist.',
            'is_active.required' => 'The active status is required.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }
}
