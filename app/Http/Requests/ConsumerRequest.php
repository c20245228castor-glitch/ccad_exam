<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsumerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = ['account_number', 'consumer_name', 'consumer_address'];
        $normalized = [];

        foreach ($fields as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim(preg_replace('/\s+/u', ' ', $this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $consumer = $this->route('consumer');
        $ignoreId = is_object($consumer) ? $consumer->getKey() : $consumer;

        return [
            'account_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('consumers', 'account_number')
                    ->ignore($ignoreId, 'consumer_id'),
            ],
            'consumer_name' => ['required', 'string', 'max:255'],
            'consumer_address' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.required' => 'The account number is required.',
            'account_number.unique' => 'This account number is already registered.',
            'consumer_name.required' => 'The consumer name is required.',
            'consumer_address.required' => 'The consumer address is required.',
        ];
    }
}
