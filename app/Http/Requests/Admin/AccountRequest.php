<?php

namespace App\Http\Requests\Admin;

use App\Models\Agent;
use App\Models\Subagent;
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
            // Username is the store handle; url-safe and unique across the username+slug namespace.
            'username' => $this->model()::handleRules(required: false),
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

    /** @return class-string<Agent|Subagent> */
    private function model(): string
    {
        return $this->isSubagent() ? Subagent::class : Agent::class;
    }
}
