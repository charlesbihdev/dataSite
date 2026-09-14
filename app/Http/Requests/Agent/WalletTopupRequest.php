<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class WalletTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('agent') !== null;
    }

    /**
     * Gateway-specific min/max are enforced in WalletTopupService against the live config; here we
     * only guarantee a sane, positive amount. The amount is the only thing we take from the client.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
        ];
    }
}
