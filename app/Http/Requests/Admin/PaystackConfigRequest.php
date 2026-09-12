<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaystackConfigRequest extends FormRequest
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
            'public_key' => ['nullable', 'string', 'max:255'],
            // Blank secrets on update keep the stored value.
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_live' => ['boolean'],
            'currency' => ['required', 'string', 'max:8'],
            'min_topup' => ['required', 'numeric', 'min:1'],
            'max_topup' => ['required', 'numeric', 'gte:min_topup'],
            'charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
