<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subagent "forgot password" on D2, on the subagents broker — Fortify's flow is agent-only.
 */
class PasswordResetLinkController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('subagent/auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker('subagents')->sendResetLink($request->only('email'));

        // Same reply whether or not the email exists, so the form can't be used to enumerate accounts.
        return back()->with('status', 'If a reseller account uses that email, a reset link is on its way.');
    }
}
