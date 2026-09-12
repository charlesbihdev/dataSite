<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use App\Models\Subagent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the subagent login form (agent-store domain).
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('subagent/auth/login');
    }

    /**
     * Handle a subagent login request. Accepts phone (primary), email, or username.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = (string) $request->input('login');

        $subagent = Subagent::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->orWhere('phone', $login)
            ->first();

        if ($subagent && Hash::check((string) $request->input('password'), $subagent->password)) {
            Auth::guard('subagent')->login($subagent, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('subagent.dashboard'));
        }

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->onlyInput('login');
    }

    /**
     * Log the subagent out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('subagent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('subagent.login');
    }
}
