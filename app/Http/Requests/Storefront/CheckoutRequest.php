<?php

namespace App\Http\Requests\Storefront;

use App\Support\GhanaMobileNetwork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A public storefront purchase. The buyer is anonymous, so authorization is open; the finer checks
 * (number matches network, package is on the store) happen in StorefrontCheckoutService.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'beneficiary_phone' => ['required', 'string', 'max:20'],
            'network' => ['required', Rule::in([GhanaMobileNetwork::MTN, GhanaMobileNetwork::TELECEL, GhanaMobileNetwork::AT])],
            'capacity_gb' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
