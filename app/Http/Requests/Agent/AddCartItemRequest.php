<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'beneficiary_phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'bundle_size' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'beneficiary_phone.regex' => 'Enter a valid 10-digit phone number starting with 0.',
        ];
    }
}
