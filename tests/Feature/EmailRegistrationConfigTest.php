<?php

namespace Tests\Feature;

use App\Models\EmailConfig;
use App\Models\RegistrationConfig;
use App\Notifications\TestEmailNotification;
use App\Services\Mail\DbMailConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailRegistrationConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_config_saves_and_encrypts_password(): void
    {
        $this->put('/admin/settings/email', [
            'from_email' => 'noreply@datasite.gh',
            'from_name' => 'DataSite',
            'smtp_enabled' => true,
            'smtp_host' => 'smtp.mailgun.org',
            'smtp_port' => 587,
            'smtp_username' => 'postmaster',
            'smtp_password' => 'super-secret',
            'smtp_encryption' => 'tls',
            'is_active' => true,
        ])->assertRedirect();

        $row = EmailConfig::current();
        $this->assertSame('super-secret', $row->smtp_password); // decrypts back
        $raw = (string) DB::table('email_configs')->where('id', $row->id)->value('smtp_password');
        $this->assertNotSame('super-secret', $raw); // encrypted at rest
    }

    public function test_blank_password_keeps_existing(): void
    {
        EmailConfig::create([
            'from_email' => 'a@b.c', 'from_name' => 'X', 'smtp_enabled' => true, 'smtp_host' => 'h',
            'smtp_port' => 587, 'smtp_username' => 'u', 'smtp_password' => 'keepme', 'smtp_encryption' => 'tls', 'is_active' => true,
        ]);

        $this->put('/admin/settings/email', [
            'from_email' => 'a@b.c', 'from_name' => 'Y', 'smtp_enabled' => true, 'smtp_host' => 'h',
            'smtp_port' => 587, 'smtp_username' => 'u', 'smtp_password' => '', 'smtp_encryption' => 'tls', 'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('keepme', EmailConfig::current()->smtp_password);
    }

    public function test_test_email_is_sent_via_notification(): void
    {
        Notification::fake();

        $this->post('/admin/settings/email/test', ['email' => 'me@example.com'])->assertRedirect();

        Notification::assertSentOnDemand(TestEmailNotification::class);
    }

    public function test_mail_configurator_points_mailer_at_db_settings(): void
    {
        EmailConfig::create([
            'from_email' => 'from@datasite.gh', 'from_name' => 'DS', 'smtp_enabled' => true, 'smtp_host' => 'smtp.db.test',
            'smtp_port' => 2525, 'smtp_username' => 'dbuser', 'smtp_password' => 'dbpass', 'smtp_encryption' => 'ssl', 'is_active' => true,
        ]);

        app(DbMailConfigurator::class)->apply();

        $this->assertSame('smtp', Config::get('mail.default'));
        $this->assertSame('smtp.db.test', Config::get('mail.mailers.smtp.host'));
        $this->assertSame(2525, Config::get('mail.mailers.smtp.port'));
        $this->assertSame('ssl', Config::get('mail.mailers.smtp.scheme'));
        $this->assertSame('from@datasite.gh', Config::get('mail.from.address'));
    }

    public function test_registration_config_saves(): void
    {
        $this->put('/admin/settings/registration', ['registration_fee' => 25.5, 'is_enabled' => false])->assertRedirect();

        $row = RegistrationConfig::current();
        $this->assertSame('25.50', $row->registration_fee);
        $this->assertFalse($row->is_enabled);
    }

    public function test_settings_page_loads(): void
    {
        $this->get('/admin/settings')->assertOk();
    }
}
