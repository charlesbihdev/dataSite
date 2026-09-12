<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MoolreConfigRequest extends FormRequest
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
        // Moolre needs username + account number + public key to be usable, so they're required
        // whenever it's switched on.
        $requiredIfActive = $this->boolean('is_active') ? 'required' : 'nullable';

        return [
            'public_key' => [$requiredIfActive, 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'currency' => ['required', 'string', 'max:8'],
            'moolre_username' => [$requiredIfActive, 'string', 'max:255'],
            'moolre_account_number' => [$requiredIfActive, 'string', 'max:255'],
        ];
    }
}
