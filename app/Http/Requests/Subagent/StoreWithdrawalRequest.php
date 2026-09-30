<?php

namespace App\Http\Requests\Subagent;

use App\Models\WithdrawalConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWithdrawalRequest extends FormRequest
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
        $amount = ['required', 'numeric', 'min:'.WithdrawalConfig::minAmount()];
        if (($max = WithdrawalConfig::maxAmount()) !== null) {
            $amount[] = 'max:'.$max;
        }

        return [
            'method' => ['required', 'string', Rule::in(array_keys(config('withdrawals.methods')))],
            'amount' => $amount,
            'destination' => ['required', 'string', 'max:255'],
        ];
    }
}
