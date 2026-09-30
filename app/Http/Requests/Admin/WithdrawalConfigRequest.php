<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalConfigRequest extends FormRequest
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
            'min_amount' => ['required', 'numeric', 'min:0.01'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount'],
        ];
    }
}
