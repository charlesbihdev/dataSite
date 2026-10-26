<?php

namespace Tests\Feature\Auth;

use App\Models\Agent;
use App\Models\Subagent;
use App\Notifications\SubagentResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubagentPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function subagent(string $email = 'kofi@example.com'): Subagent
    {
        $agent = Agent::create([
            'name' => 'Agent', 'phone' => '0550000001', 'email' => $email, 'password' => 'secret', 'is_active' => true,
        ]);

        return Subagent::create([
            'agent_id' => $agent->id,
            'name' => 'Kofi Sub',
            'phone' => '0551111111',
            'email' => $email,
            'username' => 'kofisub',
            'password' => 'secret123',
            'is_active' => true,
        ]);
    }

    public function test_forgot_password_screen_renders(): void
    {
        $this->get(route('subagent.password.request'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('subagent/auth/forgot-password'));
    }

    public function test_reset_link_is_sent_to_the_subagent_not_the_agent_sharing_the_email(): void
    {
        Notification::fake();

        $subagent = $this->subagent();

        $this->post(route('subagent.password.email'), ['email' => $subagent->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($subagent, SubagentResetPassword::class, function ($notification) use ($subagent) {
            $url = $notification->toMail($subagent)->actionUrl;

            return str_starts_with($url, route('subagent.password.reset', $notification->token));
        });
        Notification::assertNotSentTo($subagent->agent, SubagentResetPassword::class);
    }

    public function test_reset_mail_reveals_neither_the_platform_nor_the_agent_reset_page(): void
    {
        config(['app.name' => 'HiddenPlatformBrand', 'mail.from.name' => 'Agentshub.Live']);

        $subagent = $this->subagent();
        $message = (new SubagentResetPassword('token'))->toMail($subagent);
        $html = (string) $message->render();

        $this->assertSame(route('subagent.password.reset', ['token' => 'token', 'email' => $subagent->email]), $message->actionUrl);
        $this->assertStringNotContainsString('HiddenPlatformBrand', $html);
        $this->assertStringNotContainsString(route('password.request'), $html);
        $this->assertStringNotContainsString(route('register'), $html);
    }

    public function test_unknown_email_gets_the_same_generic_status(): void
    {
        Notification::fake();

        $this->post(route('subagent.password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_reset_screen_renders(): void
    {
        $this->get(route('subagent.password.reset', ['token' => 'abc', 'email' => 'kofi@example.com']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('subagent/auth/reset-password')
                ->where('token', 'abc')
                ->where('email', 'kofi@example.com'));
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $subagent = $this->subagent();

        $this->post(route('subagent.password.email'), ['email' => $subagent->email]);

        Notification::assertSentTo($subagent, SubagentResetPassword::class, function ($notification) use ($subagent) {
            $this->post(route('subagent.password.update'), [
                'token' => $notification->token,
                'email' => $subagent->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasNoErrors()->assertRedirect(route('subagent.login'));

            return true;
        });

        $this->assertTrue(Hash::check('new-password', $subagent->fresh()->password));
        $this->assertTrue(Hash::check('secret', $subagent->agent->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $subagent = $this->subagent();

        $this->post(route('subagent.password.update'), [
            'token' => 'invalid-token',
            'email' => $subagent->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');
    }
}
