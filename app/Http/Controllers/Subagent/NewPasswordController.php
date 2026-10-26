<?php

namespace App\Http\Controllers\Subagent;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Subagent;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    use PasswordValidationRules;

    public function create(Request $request, string $token): Response
    {
        return Inertia::render('subagent/auth/reset-password', [
            'email' => $request->string('email')->value(),
            'token' => $token,
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::broker('subagents')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Subagent $subagent, string $password) {
                $subagent->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($subagent));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('subagent.login')->with('status', __($status));
    }
}
