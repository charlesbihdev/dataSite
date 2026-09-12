<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DbhConnectionRequest extends FormRequest
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
            'base_url' => ['required', 'url', 'max:255'],
            // Optional on update: blank means keep the stored key.
            'api_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
