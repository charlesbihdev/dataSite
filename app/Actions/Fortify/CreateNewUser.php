<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Agent;
use App\Models\PricingTier;
use App\Models\Subagent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            'username' => ['required', 'string', 'max:255', 'unique:agents'],
            'phone' => ['required', 'string', 'max:20', 'unique:agents'],
            'password' => $this->passwordRules(),
        ])->validate();

        $defaultTier = PricingTier::where('is_default', true)->first();

        // Generate slug from username
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', $input['username']));

        // Ensure slug is unique. If not, append a random string (or fail, but since username is unique, this should usually be fine unless a subagent has it)
        if (Agent::where('slug', $slug)->exists() || Subagent::where('slug', $slug)->exists()) {
            $slug .= '-'.strtolower(Str::random(4));
        }

        $agent = Agent::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'username' => $input['username'],
            'phone' => $input['phone'],
            'slug' => $slug,
            'password' => $input['password'],
            'pricing_tier_id' => $defaultTier?->id,
            'is_active' => true,
        ]);

        // Provision a wallet for the new agent
        $agent->wallet()->create(['balance' => 0]);

        return $agent;
    }
}
