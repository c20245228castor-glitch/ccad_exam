<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ConsumerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge([
                'email' => mb_strtolower(trim($this->input('email'))),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
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
            'email' => [
                $isUpdate ? 'prohibited' : 'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                $isUpdate ? 'prohibited' : 'required',
                'string',
                'confirmed',
                Password::defaults(),
            ],
            'password_confirmation' => [
                $isUpdate ? 'prohibited' : 'required',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'consumer_name.required' => 'The consumer name is required.',
            'consumer_name.string' => 'The consumer name must be a valid string.',
            'consumer_name.max' => 'The consumer name may not exceed 255 characters.',
            'consumer_address.required' => 'The consumer address is required.',
            'consumer_address.string' => 'The consumer address must be a valid string.',
            'consumer_address.max' => 'The consumer address may not exceed 255 characters.',
            'email.required' => 'A Gmail or email address is required for the consumer login.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email address is already associated with an account.',
            'password.required' => 'A password is required for the consumer login.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password_confirmation.required' => 'Confirm the consumer login password.',
        ];
    }
}
