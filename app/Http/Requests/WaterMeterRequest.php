<?php

namespace App\Http\Requests;

use App\Models\WaterMeter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WaterMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'consumer_id' => [
                'required',
                'exists:consumers,consumer_id',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];

        $meterId = $this->route('id');
        $meter = $meterId ? WaterMeter::query()->find($meterId) : null;

        if ($meter === null || (int) $meter->consumer_id !== (int) $this->input('consumer_id')) {
            $uniqueConsumer = Rule::unique('water_meters', 'consumer_id');

            if ($meter !== null) {
                $uniqueConsumer->ignore($meter->getKey(), 'meter_id');
            }

            $rules['consumer_id'][] = $uniqueConsumer;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'consumer_id.required' => 'The consumer is required.',
            'consumer_id.exists' => 'The selected consumer does not exist.',
            'consumer_id.unique' => 'This consumer already has a water meter.',
            'is_active.required' => 'The active status is required.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }
}
