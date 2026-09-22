<?php

namespace App\Http\Requests\Subagent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('subagent') !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'whatsapp_group_link' => ['nullable', 'url', 'max:255'],
        ];
    }
}
