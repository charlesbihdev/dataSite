<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Notifications\TestEmailNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Shared email chrome carries the sender name only — never APP_NAME or APP_URL, which would reveal
 * the admin/agent domain to anyone a message is forwarded to.
 */
class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: callable(): Notification}> */
    public static function notifications(): array
    {
        return [
            'smtp test' => [fn () => new TestEmailNotification],
            'password reset' => [fn () => new ResetPassword('token')],
        ];
    }

    #[DataProvider('notifications')]
    public function test_shared_layout_uses_the_sender_name_not_the_platform(callable $make): void
    {
        config(['app.name' => 'HiddenPlatformBrand', 'app.url' => 'https://hidden-platform.test', 'mail.from.name' => 'Agentshub.Live']);

        $message = $make()->toMail(Agent::factory()->create());
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());

        foreach ([$html, $text] as $body) {
            $this->assertStringContainsString('Agentshub.Live', $body);
            $this->assertStringNotContainsString('HiddenPlatformBrand', $body);
            $this->assertStringNotContainsString('hidden-platform.test', $body);
        }
    }
}
