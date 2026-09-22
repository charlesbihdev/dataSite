<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubagentPortalTest extends TestCase
{
    use RefreshDatabase;

    private function inviter(array $overrides = []): Agent
    {
        return Agent::factory()->create(array_merge(['slug' => 'charlesbih', 'store_name' => 'Charles Data', 'is_active' => true], $overrides));
    }

    public function test_register_page_loads_for_a_valid_agent_ref(): void
    {
        $this->inviter();

        $this->get(route('subagent.register', ['ref' => 'charlesbih']))->assertInertia(
            fn (Assert $page) => $page
                ->component('subagent/auth/register')
                ->where('ref', 'charlesbih')
                ->where('inviter.name', 'Charles Data')
        );
    }

    public function test_register_page_404s_for_an_unknown_or_inactive_ref(): void
    {
        $this->get(route('subagent.register', ['ref' => 'nobody']))->assertNotFound();

        $this->inviter(['slug' => 'off-agent', 'is_active' => false]);
        $this->get(route('subagent.register', ['ref' => 'off-agent']))->assertNotFound();
    }

    public function test_registration_creates_a_subagent_under_the_agent_and_logs_in(): void
    {
        $agent = $this->inviter();

        $this->post(route('subagent.register.store', ['ref' => 'charlesbih']), [
            'ref' => 'charlesbih',
            'name' => 'Ama Sub',
            'email' => 'ama@example.com',
            'username' => 'amasub',
            'phone' => '0248887777',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertRedirect(route('subagent.dashboard'));

        $subagent = Subagent::query()->where('username', 'amasub')->firstOrFail();
        $this->assertSame($agent->id, $subagent->agent_id);
        $this->assertTrue((bool) $subagent->is_active);
        $this->assertSame('amasub', $subagent->slug); // slug is exactly the username — no silent suffix
        $this->assertNotNull($subagent->wallet); // wallet provisioned
        $this->assertAuthenticatedAs($subagent, 'subagent');
    }

    public function test_registration_rejects_a_username_that_clashes_with_another_subagents_store_link(): void
    {
        $agent = $this->inviter();
        Subagent::create([
            'name' => 'First', 'phone' => '0240000001', 'username' => 'taken', 'slug' => 'taken',
            'password' => 'secret', 'agent_id' => $agent->id, 'is_active' => true,
        ]);

        // A clash with the taken 'taken' store link must return a clear error, not a silent suffix.
        $this->from(route('subagent.register', ['ref' => 'charlesbih']))
            ->post(route('subagent.register.store', ['ref' => 'charlesbih']), [
                'ref' => 'charlesbih',
                'name' => 'Clashy',
                'email' => 'clash@example.com',
                'username' => 'taken',
                'phone' => '0249990000',
                'password' => 'Secret123!',
                'password_confirmation' => 'Secret123!',
            ])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('subagents', ['email' => 'clash@example.com']);
    }

    public function test_registration_rejects_a_username_with_unpermitted_characters(): void
    {
        $this->inviter();

        $this->post(route('subagent.register.store', ['ref' => 'charlesbih']), [
            'ref' => 'charlesbih',
            'name' => 'Dotty',
            'email' => 'dot@example.com',
            'username' => 'ta.ken', // dot is not allowed
            'phone' => '0249990000',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('subagents', ['email' => 'dot@example.com']);
    }

    public function test_a_username_matching_an_agents_slug_is_allowed(): void
    {
        $this->inviter(); // agent store link is 'charlesbih' (a separate domain: /buy/{slug})

        // A subagent MAY take 'charlesbih' — their store link lives on the D3 domain (/{slug}), so it
        // never collides with the agent's D2 storefront. Cross-tier is not a clash.
        $this->post(route('subagent.register.store', ['ref' => 'charlesbih']), [
            'ref' => 'charlesbih',
            'name' => 'Same Slug',
            'email' => 'same@example.com',
            'username' => 'charlesbih',
            'phone' => '0247778888',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertRedirect(route('subagent.dashboard'));

        $this->assertDatabaseHas('subagents', ['username' => 'charlesbih', 'slug' => 'charlesbih']);
    }

    public function test_suspended_subagent_cannot_log_in(): void
    {
        $agent = $this->inviter();
        Subagent::create([
            'name' => 'Susp', 'phone' => '0245551111', 'username' => 'susp', 'slug' => 'susp',
            'password' => 'Secret123!', 'agent_id' => $agent->id, 'is_active' => false,
        ]);

        $this->from(route('subagent.login'))
            ->post(route('subagent.login.store'), ['login' => 'susp', 'password' => 'Secret123!'])
            ->assertSessionHasErrors('login');

        $this->assertGuest('subagent');
    }

    public function test_registration_requires_a_valid_agent_ref(): void
    {
        $this->post(route('subagent.register.store', ['ref' => 'nobody']), [
            'ref' => 'nobody', 'name' => 'X', 'email' => 'x@example.com', 'username' => 'x',
            'phone' => '0240000000', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        ])->assertNotFound();

        $this->assertDatabaseCount('subagents', 0);
    }

    public function test_subagent_dashboard_loads_for_the_authenticated_subagent(): void
    {
        $agent = $this->inviter();
        $subagent = Subagent::create([
            'name' => 'Kofi', 'phone' => '0245550000', 'username' => 'kofi', 'slug' => 'kofi',
            'password' => 'secret', 'agent_id' => $agent->id, 'is_active' => true,
        ]);

        $this->actingAs($subagent, 'subagent')
            ->get(route('subagent.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('subagent/dashboard')->has('earnings')->has('greeting'));
    }
}
