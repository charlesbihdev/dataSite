<?php

namespace App\Concerns;

use App\Models\Agent;
use Closure;
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
            // Username is the store handle; both required and unique across the username+slug namespace.
            'username' => ['required', 'string', 'max:255', $this->handleRule($userId)],
            'slug' => ['required', 'string', 'max:255', $this->handleRule($userId)],
        ];
    }

    /** Reject a handle already used as a username or slug by another agent. */
    private function handleRule(?int $userId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
            if (Agent::handleTaken((string) $value, $userId)) {
                $label = $attribute === 'slug' ? 'store handle' : 'username';
                $fail("That {$label} is already taken. Please choose a different one.");
            }
        };
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
