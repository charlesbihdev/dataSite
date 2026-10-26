<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * The stock notification links to Fortify's password.reset (the agent page on D1); subagents reset on D2.
 */
class SubagentResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return route('subagent.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
