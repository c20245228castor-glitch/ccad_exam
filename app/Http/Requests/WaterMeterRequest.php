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
        if (is_string($this->input('meter_serial'))) {
            $this->merge([
                'meter_serial' => strtoupper(trim($this->input('meter_serial'))),
            ]);
        }
    }

    public function rules(): array
    {
        $meter = $this->route('water_meter');
        $ignoreId = is_object($meter) ? $meter->getKey() : $meter;

        return [
            'consumer_id' => [
                'required',
                'integer',
                'exists:consumers,consumer_id',
            ],
            'meter_serial' => [
                'required',
                'string',
                'min:3',
                'max:50',
                Rule::unique('water_meters', 'meter_serial')
                    ->ignore($ignoreId, 'meter_id'),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'consumer_id.required' => 'Please select a consumer.',
            'consumer_id.exists' => 'The selected consumer does not exist.',
            'meter_serial.required' => 'The meter serial number is required.',
            'meter_serial.unique' => 'This meter serial number is already registered.',
            'is_active.required' => 'Please select the meter status.',
            'is_active.boolean' => 'The meter status must be active or inactive.',
        ];
    }
}
