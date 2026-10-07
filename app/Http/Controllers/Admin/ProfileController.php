<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin profile management: updating contact details (name, email, phone, username)
 * and changing the admin password with current password verification.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $admin = $request->user('admin');

        return Inertia::render('admin/profile', [
            'profile' => [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'username' => $admin->username,
                'lastLoginAt' => $admin->last_login_at?->toDayDateTimeString(),
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('admins', 'phone')->ignore($admin->id)],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('admins', 'username')->ignore($admin->id)],
        ]);

        $admin->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile updated successfully.']);

        return back();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user('admin')->update([
            'password' => $request->string('password')->value(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password updated successfully.']);

        return back();
    }
}

