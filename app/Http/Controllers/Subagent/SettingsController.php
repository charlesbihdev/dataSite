<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Models\Subagent;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subagent's account settings — profile (name, contact, store handle) and password — scoped to
 * the subagent guard. A single self-contained page rather than the agent's multi-tab settings shell.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $subagent = $request->user('subagent');

        return Inertia::render('subagent/settings', [
            'profile' => [
                'name' => $subagent->name,
                'email' => $subagent->email,
                'phone' => $subagent->phone,
                'username' => $subagent->username,
                'slug' => $subagent->slug,
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $subagent = $request->user('subagent');

        // Username is the store handle — normalise both up front and validate those exact values.
        $request->merge([
            'username' => Subagent::slugFor((string) $request->input('username')),
            'slug' => Subagent::slugFor((string) $request->input('slug')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('subagents', 'email')->ignore($subagent->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('subagents', 'phone')->ignore($subagent->id)],
            // Both required and unique across the username+slug namespace.
            'username' => ['required', 'string', 'max:255', $this->handleRule($subagent->id)],
            'slug' => ['required', 'string', 'max:255', $this->handleRule($subagent->id)],
        ]);

        $subagent->fill($data);

        // The QR encodes the /{slug} link, so a handle change stales it — clear it and it regenerates.
        if ($subagent->isDirty('slug')) {
            $subagent->referral_qr = null;
        }

        $subagent->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile updated.']);

        return to_route('subagent.settings');
    }

    /** Reject a handle already used as a username or slug by another subagent. */
    private function handleRule(int $subagentId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($subagentId): void {
            if (Subagent::handleTaken((string) $value, $subagentId)) {
                $label = $attribute === 'slug' ? 'store handle' : 'username';
                $fail("That {$label} is already taken. Please choose a different one.");
            }
        };
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:subagent'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user('subagent')->update(['password' => $request->string('password')->value()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password updated.']);

        return back();
    }
}
