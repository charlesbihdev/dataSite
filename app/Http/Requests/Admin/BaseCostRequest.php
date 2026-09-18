<?php

namespace App\Http\Requests\Admin;

use App\Models\BaseCost;
use App\Services\Pricing\BandOverlap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BaseCostRequest extends FormRequest
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
            'network' => ['required', Rule::in(['mtn', 'telecel', 'at'])],
            'min_gb' => ['required', 'numeric', 'min:0'],
            'max_gb' => ['required', 'numeric', 'gte:min_gb'],
            'cost_per_gb' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Reject a range that touches any other active cost band on the same network. An inactive
     * band can never be resolved, so it does not need to reserve a range.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['network', 'min_gb', 'max_gb'])) {
                return;
            }

            $existing = $this->route('baseCost');

            $willBeActive = $this->has('is_active')
                ? $this->boolean('is_active')
                : ($existing?->is_active ?? true);

            if (! $willBeActive) {
                return;
            }

            $overlap = app(BandOverlap::class);

            $conflict = $overlap->conflicting(
                BaseCost::query()->where('network', (string) $this->input('network')),
                (float) $this->input('min_gb'),
                (float) $this->input('max_gb'),
                $existing?->id,
            );

            if ($conflict !== null) {
                $validator->errors()->add(
                    'max_gb',
                    "This range overlaps an active band ({$overlap->describe($conflict)} GB). Bands must not overlap.",
                );
            }
        });
    }
}
