<?php

namespace App\Http\Requests\Admin;

use App\Models\TierPrice;
use App\Services\Pricing\BandOverlap;
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
     * Two cross-field guardrails: bands may not overlap, and a selling rate may never sit below
     * the base cost it covers. Both need the range fields, so bail if those already failed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['network', 'min_gb', 'max_gb'])) {
                return;
            }

            $network = (string) $this->input('network');
            $minGb = (float) $this->input('min_gb');
            $maxGb = (float) $this->input('max_gb');

            $this->guardAgainstOverlap($validator, $network, $minGb, $maxGb);

            $floor = app(CostFloor::class)->forRange($network, $minGb, $maxGb);

            if ($floor !== null && (float) $this->input('price_per_gb') < $floor) {
                $validator->errors()->add(
                    'price_per_gb',
                    "Price per GB must be at least the base cost (GHS {$floor}) for this band.",
                );
            }
        });
    }

    /**
     * Reject a range that touches any other active band on the same tier + network. An inactive
     * band can never be resolved, so it does not need to reserve a range.
     */
    private function guardAgainstOverlap(Validator $validator, string $network, float $minGb, float $maxGb): void
    {
        $existing = $this->route('tierPrice');

        $willBeActive = $this->has('is_active')
            ? $this->boolean('is_active')
            : ($existing?->is_active ?? true);

        if (! $willBeActive) {
            return;
        }

        $overlap = app(BandOverlap::class);

        $conflict = $overlap->conflicting(
            TierPrice::query()
                ->where('pricing_tier_id', $this->integer('pricing_tier_id'))
                ->where('network', $network),
            $minGb,
            $maxGb,
            $existing?->id,
        );

        if ($conflict !== null) {
            $validator->errors()->add(
                'max_gb',
                "This range overlaps an active band ({$overlap->describe($conflict)} GB). Bands must not overlap.",
            );
        }
    }
}
