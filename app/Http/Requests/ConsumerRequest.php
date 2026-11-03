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
        $this->merge([
            'account_number' => trim((string) $this->input('account_number', '')),
            'consumer_name' => trim((string) $this->input('consumer_name', '')),
            'consumer_address' => trim((string) $this->input('consumer_address', '')),
        ]);
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $accountNumberRules = [
            $isUpdate ? 'sometimes' : 'required',
            'string',
            'max:255',
        ];

        $uniqueAccountNumber = Rule::unique('consumers', 'account_number');
        if ($isUpdate) {
            $uniqueAccountNumber->ignore($this->route('id'), 'consumer_id');
        }
        $accountNumberRules[] = $uniqueAccountNumber;

        return [
            'account_number' => $accountNumberRules,
            'consumer_name' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255',
            ],
            'consumer_address' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.required' => 'The account number is required.',
            'account_number.string' => 'The account number must be a valid string.',
            'account_number.max' => 'The account number may not exceed 255 characters.',
            'account_number.unique' => 'This account number has already been taken.',
            'consumer_name.required' => 'The consumer name is required.',
            'consumer_name.string' => 'The consumer name must be a valid string.',
            'consumer_name.max' => 'The consumer name may not exceed 255 characters.',
            'consumer_address.required' => 'The consumer address is required.',
            'consumer_address.string' => 'The consumer address must be a valid string.',
            'consumer_address.max' => 'The consumer address may not exceed 255 characters.',
        ];
    }
}