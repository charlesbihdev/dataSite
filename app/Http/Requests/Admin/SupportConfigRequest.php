<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SupportConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: admin policy once multi-guard auth lands.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s-]{9,19}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp_number.regex' => 'Enter a phone number, e.g. 0241234567 or +233241234567.',
        ];
    }
}
