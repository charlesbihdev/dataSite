<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Agent;
use App\Models\PricingTier;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): Agent
    {
        // The username IS the store handle (/buy/{slug}, D2), so normalise it into a url-safe form up
        // front and validate the exact value the link will use — no separate slug that can drift.
        $input['username'] = Agent::slugFor((string) ($input['username'] ?? ''));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:agents'],
            'username' => [
                'required', 'string', 'max:255',
                // Unique across the whole handle namespace (username OR slug) among OTHER AGENTS only —
                // subagent handles live on a separate domain (D3: /{slug}), so a shared handle there is
                // not a clash. Reject a real clash outright, never a silent suffix.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Agent::handleTaken((string) $value)) {
                        $fail('That username is already taken. Please choose a different one for your store link.');
                    }
                },
            ],
            'phone' => ['required', 'string', 'max:20', 'unique:agents'],
            'password' => $this->passwordRules(),
        ])->validate();

        $defaultTier = PricingTier::where('is_default', true)->first();

        $agent = Agent::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'username' => $input['username'],
            'phone' => $input['phone'],
            // The handle starts identical to the username; the agent can change it later in settings.
            'slug' => $input['username'],
            'password' => $input['password'],
            'pricing_tier_id' => $defaultTier?->id,
            'is_active' => true,
        ]);

        // Provision a wallet for the new agent
        $agent->wallet()->create(['balance' => 0]);

        return $agent;
    }
}
