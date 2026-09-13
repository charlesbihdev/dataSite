<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentSubagentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_the_agents_subagents_with_referral_link(): void
    {
        $agent = Agent::factory()->create(['slug' => 'sprintdata']);
        $agent->subagents()->create([
            'name' => 'Karl Seven', 'username' => 'karl2004', 'phone' => '0594898895',
            'slug' => 'karl', 'password' => 'secret', 'is_active' => true,
        ]);
        // A different agent's subagent must NOT appear.
        Agent::factory()->create()->subagents()->create([
            'name' => 'Other', 'phone' => '0201111111', 'password' => 'secret', 'is_active' => true,
        ]);

        $this->actingAs($agent, 'agent')->get(route('agent.subagents'))->assertInertia(
            fn (Assert $page) => $page
                ->component('agent/subagents')
                ->where('referralUrl', fn ($url) => str_contains($url, 'ref=sprintdata'))
                ->has('subagents.data', 1)
                ->where('subagents.data.0.name', 'Karl Seven')
                ->where('subagents.data.0.storeStatus', 'active')
                ->where('subagents.data.0.accountStatus', 'active')
        );
    }

    public function test_a_subagent_without_a_slug_reads_store_inactive(): void
    {
        $agent = Agent::factory()->create();
        $agent->subagents()->create([
            'name' => 'No Store', 'phone' => '0207778889', 'password' => 'secret', 'is_active' => true,
        ]);

        $this->actingAs($agent, 'agent')->get(route('agent.subagents'))->assertInertia(
            fn (Assert $page) => $page->where('subagents.data.0.storeStatus', 'inactive')
        );
    }
}
