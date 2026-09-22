<?php

namespace App\Http\Requests\Admin;

use App\Models\Agent;
use App\Models\Subagent;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates creating an agent or subagent from the admin Accounts screen. The route's {type}
 * segment decides which table uniqueness is checked against and which parent link is required
 * (agents need a pricing tier; subagents need an owning agent).
 */
class AccountRequest extends FormRequest
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
        $table = $this->isSubagent() ? 'subagents' : 'agents';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique($table, 'phone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($table, 'email')],
            // Username is the store handle; unique across the username+slug namespace.
            'username' => ['nullable', 'string', 'max:255', $this->handleRule()],
            'password' => ['required', Password::defaults()],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];

        if ($this->isSubagent()) {
            $rules['agent_id'] = ['required', Rule::exists('agents', 'id')];
        } else {
            $rules['pricing_tier_id'] = ['nullable', Rule::exists('pricing_tiers', 'id')];
        }

        return $rules;
    }

    public function isSubagent(): bool
    {
        return $this->route('type') === 'subagents';
    }

    /** Normalise the username into a url-safe handle before validating. */
    protected function prepareForValidation(): void
    {
        if (filled($this->input('username'))) {
            $this->merge(['username' => $this->model()::slugFor((string) $this->input('username'))]);
        }
    }

    /** Reject a username already used as a username or slug by another account of this type. */
    private function handleRule(): Closure
    {
        $model = $this->model();

        return function (string $attribute, mixed $value, Closure $fail) use ($model): void {
            if ($model::handleTaken((string) $value)) {
                $fail('That username is already taken. Please choose a different one for the store link.');
            }
        };
    }

    /** @return class-string<Agent|Subagent> */
    private function model(): string
    {
        return $this->isSubagent() ? Subagent::class : Agent::class;
    }
}
