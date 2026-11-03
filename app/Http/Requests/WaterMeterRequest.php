<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WaterMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'meter_serial' => strtoupper(
                trim((string) $this->input('meter_serial', ''))
            ),
        ]);
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'consumer_id' => [
                'required',
                'exists:consumers,consumer_id',
            ],
            'meter_serial' => [
                'required',
                'string',
                'min:3',
                'max:50',
                Rule::unique('water_meters', 'meter_serial')
                    ->ignore($isUpdate ? $this->route('id') : null, 'meter_id'),
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
            'meter_serial.required' => 'The meter serial number is required.',
            'meter_serial.string' => 'The meter serial number must be a valid string.',
            'meter_serial.min' => 'The meter serial number must be at least 3 characters long.',
            'meter_serial.max' => 'The meter serial number may not exceed 50 characters.',
            'meter_serial.unique' => 'This meter serial number has already been used.',
            'is_active.required' => 'The active status is required.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }
}