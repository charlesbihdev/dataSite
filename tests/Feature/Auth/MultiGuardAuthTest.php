<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\PricingTier;
use App\Models\Subagent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MultiGuardAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_guard_authenticates_admin_model(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'phone' => '+233240000010',
            'email' => 'admin@datasite.com',
            'username' => 'superadmin',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->assertTrue(Auth::guard('admin')->attempt([
            'username' => 'superadmin',
            'password' => 'secret123',
        ]));

        $this->assertSame($admin->id, Auth::guard('admin')->id());
    }

    public function test_authenticated_admin_visiting_login_is_sent_to_dashboard_not_root(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'phone' => '+233240000010',
            'email' => 'admin@datasite.com',
            'username' => 'superadmin',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        // guest:admin bounces an already-authenticated admin — it must land on the admin
        // dashboard, not the public landing "/".
        $this->actingAs($admin, 'admin')
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_agent_guard_authenticates_agent_model(): void
    {
        $tier = PricingTier::create(['name' => 'Gold', 'is_active' => true]);
        $agent = Agent::create([
            'pricing_tier_id' => $tier->id,
            'name' => 'Agent Mensah',
            'phone' => '+233550000020',
            'email' => 'agent@datasite.com',
            'username' => 'agentmensah',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->assertTrue(Auth::guard('agent')->attempt([
            'phone' => '+233550000020',
            'password' => 'secret123',
        ]));

        $this->assertSame($agent->id, Auth::guard('agent')->id());
    }

    public function test_subagent_guard_authenticates_subagent_model(): void
    {
        $tier = PricingTier::create(['name' => 'Gold', 'is_active' => true]);
        $agent = Agent::create([
            'pricing_tier_id' => $tier->id,
            'name' => 'Agent Mensah',
            'phone' => '+233550000020',
            'email' => 'agent@datasite.com',
            'username' => 'agentmensah',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $subagent = Subagent::create([
            'agent_id' => $agent->id,
            'name' => 'Subagent Kofi',
            'phone' => '+233540000030',
            'email' => 'kofi@datasite.com',
            'username' => 'subagentkofi',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->assertTrue(Auth::guard('subagent')->attempt([
            'phone' => '+233540000030',
            'password' => 'secret123',
        ]));

        $this->assertSame($subagent->id, Auth::guard('subagent')->id());
    }
}
