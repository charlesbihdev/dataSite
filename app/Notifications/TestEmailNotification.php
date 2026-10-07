<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A one-off "your SMTP settings work" email, sent on-demand from the Email config screen via
 * Notification::route('mail', ...). Uses whatever mailer DbMailConfigurator has applied.
 */
class TestEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'E-cubetechsolutions');

        return (new MailMessage)
            ->subject("{$appName} — test email")
            ->greeting('It works.')
            ->line("This confirms your {$appName} email configuration can send mail.")
            ->line('Sent at '.now()->toDayDateTimeString().'.');
    }
}
