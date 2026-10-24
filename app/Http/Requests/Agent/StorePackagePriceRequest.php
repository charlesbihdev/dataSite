<?php

namespace App\Http\Requests\Agent;

use App\Support\GhanaMobileNetwork;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackagePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'network' => ['required', 'string', Rule::in([GhanaMobileNetwork::MTN, GhanaMobileNetwork::TELECEL, GhanaMobileNetwork::AT])],
            'capacity_gb' => ['required', 'integer', 'min:'.GhanaMobileNetwork::minGb($this->string('network')->value()), 'max:200'],
            'selling_price' => ['required', 'numeric', 'min:0.01'],
            'subagent_price' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['capacity_gb.min' => 'This network only sells bundles of :min GB and above.'];
    }
}
