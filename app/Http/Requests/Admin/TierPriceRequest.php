<?php

namespace App\Http\Requests\Admin;

use App\Services\Pricing\CostFloor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TierPriceRequest extends FormRequest
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
            'pricing_tier_id' => ['required', 'exists:pricing_tiers,id'],
            'network' => ['required', Rule::in(['mtn', 'telecel', 'at'])],
            'min_gb' => ['required', 'numeric', 'min:0'],
            'max_gb' => ['required', 'numeric', 'gte:min_gb'],
            'price_per_gb' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * The cost-floor guardrail: a selling rate may never sit below the base cost it covers.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $floor = app(CostFloor::class)->forRange(
                (string) $this->input('network'),
                (float) $this->input('min_gb'),
                (float) $this->input('max_gb'),
            );

            if ($floor !== null && (float) $this->input('price_per_gb') < $floor) {
                $validator->errors()->add(
                    'price_per_gb',
                    "Price per GB must be at least the base cost (GHS {$floor}) for this band.",
                );
            }
        });
    }
}
