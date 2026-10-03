<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates one chat turn posted by the storefront assistant widget: the new message plus the short
 * browser-kept history. History is bounded here too so a crafted client can't blow up the prompt.
 */
class AssistantChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public storefront — the surface/slug resolution guards access.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:500'],
            'history' => ['sometimes', 'array', 'max:30'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    public function history(): array
    {
        return array_values($this->input('history', []));
    }
}
