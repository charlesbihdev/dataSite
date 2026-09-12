<?php

namespace Tests\Feature\Auth;

use App\Models\Agent;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubagentAuthTest extends TestCase
{
    use RefreshDatabase;

    private function subagent(): Subagent
    {
        $agent = Agent::create([
            'name' => 'Agent', 'phone' => '0550000001', 'password' => 'secret', 'is_active' => true,
        ]);

        return Subagent::create([
            'agent_id' => $agent->id,
            'name' => 'Kofi Sub',
            'phone' => '0551111111',
            'username' => 'kofisub',
            'password' => 'secret123',
            'is_active' => true,
        ]);
    }

    public function test_login_screen_renders(): void
    {
        $this->get(route('subagent.login'))->assertOk();
    }

    public function test_guest_hitting_the_dashboard_is_sent_to_the_subagent_login(): void
    {
        // Must land on the subagent login — NOT the agent (Fortify) login.
        $this->get(route('subagent.dashboard'))
            ->assertRedirect(route('subagent.login'));
    }

    public function test_subagent_can_authenticate_with_phone(): void
    {
        $subagent = $this->subagent();

        $this->post(route('subagent.login.store'), [
            'login' => $subagent->phone,
            'password' => 'secret123',
        ])->assertRedirect(route('subagent.dashboard'));

        $this->assertAuthenticated('subagent');
        $this->assertGuest('agent');
        $this->assertGuest('admin');
    }

    public function test_subagent_can_authenticate_with_username(): void
    {
        $subagent = $this->subagent();

        $this->post(route('subagent.login.store'), [
            'login' => 'kofisub',
            'password' => 'secret123',
        ])->assertRedirect(route('subagent.dashboard'));

        $this->assertAuthenticated('subagent');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $subagent = $this->subagent();

        $this->post(route('subagent.login.store'), [
            'login' => $subagent->phone,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('subagent');
    }

    public function test_an_agent_cannot_sign_in_through_the_subagent_login(): void
    {
        // Cross-role isolation: agent credentials must not authenticate here.
        Agent::create([
            'name' => 'Real Agent', 'phone' => '0559990000', 'username' => 'realagent',
            'password' => 'secret123', 'is_active' => true,
        ]);

        $this->post(route('subagent.login.store'), [
            'login' => '0559990000',
            'password' => 'secret123',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('subagent');
    }

    public function test_subagent_can_log_out(): void
    {
        $subagent = $this->subagent();

        $this->actingAs($subagent, 'subagent')
            ->post(route('subagent.logout'))
            ->assertRedirect(route('subagent.login'));

        $this->assertGuest('subagent');
    }
}
