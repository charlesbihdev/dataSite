<?php

namespace App\Services\Mail;

use App\Models\EmailConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Points Laravel's mailer at the SMTP account stored in the database instead of .env, so admins can
 * change the sending account from the backoffice with no deploy. Applied once at boot; if the config
 * row is missing, inactive, or SMTP is off, the framework's .env defaults are left untouched.
 */
class DbMailConfigurator
{
    public function apply(): void
    {
        try {
            if (! Schema::hasTable('email_configs')) {
                return;
            }

            $config = EmailConfig::current();
            if ($config === null || ! $config->is_active) {
                return;
            }

            // From address applies whether or not SMTP is overridden.
            Config::set('mail.from.address', $config->from_email);
            Config::set('mail.from.name', $config->from_name);

            if (! $config->smtp_enabled || (string) $config->smtp_host === '') {
                return;
            }

            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $config->smtp_host);
            Config::set('mail.mailers.smtp.port', $config->smtp_port);
            Config::set('mail.mailers.smtp.username', $config->smtp_username);
            Config::set('mail.mailers.smtp.password', $config->smtp_password);
            Config::set('mail.mailers.smtp.scheme', $config->smtp_encryption === 'none' ? null : $config->smtp_encryption);
        } catch (Throwable) {
            // Never let mail config break the app boot — fall back to .env defaults.
        }
    }
}
