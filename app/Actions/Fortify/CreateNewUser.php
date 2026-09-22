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
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:agents'],
            // Username is the store handle.
            'username' => Agent::handleRules(),
            'phone' => ['required', 'string', 'max:20', 'unique:agents'],
            'password' => $this->passwordRules(),
        ])->validate();

        $defaultTier = PricingTier::where('is_default', true)->first();

        $agent = Agent::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'username' => $input['username'],
            'phone' => $input['phone'],
            // Handle starts equal to the username; changed later in settings.
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
