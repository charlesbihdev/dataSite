<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * A bulk action over a set of selected agent/subagent ids: reset password, suspend, activate,
 * delete, or export to CSV. Reset requires a shared new password.
 */
class BulkAccountRequest extends FormRequest
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
            'action' => ['required', Rule::in(['reset', 'suspend', 'activate', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'password' => [Rule::requiredIf($this->input('action') === 'reset'), 'nullable', Password::defaults()],
        ];
    }
}
