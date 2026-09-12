<?php

namespace App\Http\Requests\Admin;

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
}
