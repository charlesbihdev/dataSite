<?php

namespace App\Concerns;

use App\Models\Agent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        $ignore = fn () => $userId === null ? Rule::unique(Agent::class) : Rule::unique(Agent::class)->ignore($userId);

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'phone' => ['required', 'string', 'max:20', $ignore()],
            'username' => ['nullable', 'string', 'max:255', $ignore()],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', $ignore()],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(Agent::class)
                : Rule::unique(Agent::class)->ignore($userId),
        ];
    }
}
