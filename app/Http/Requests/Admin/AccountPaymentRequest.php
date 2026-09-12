<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A manual deposit-wallet adjustment from the admin: positive tops the account up, negative
 * deducts. Mirrors DBH's "Add payment" (which also accepts negatives).
 */
class AccountPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['amount.not_in' => 'Enter a non-zero amount.'];
    }
}
