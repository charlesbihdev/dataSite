<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\SupportConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportWhatsappTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_the_support_whatsapp_number(): void
    {
        $this->actingAsAdmin();

        $this->put('/admin/settings/support', ['whatsapp_number' => '024 123 4567'])->assertRedirect();

        $this->assertSame('024 123 4567', SupportConfig::current()->whatsapp_number);
        $this->get('/admin/settings')->assertInertia(
            fn (Assert $page) => $page->where('support.whatsappNumber', '024 123 4567')
        );
    }

    public function test_admin_can_clear_the_number(): void
    {
        $this->actingAsAdmin();
        SupportConfig::create(['whatsapp_number' => '0241234567']);

        $this->put('/admin/settings/support', ['whatsapp_number' => ''])->assertRedirect();

        $this->assertNull(SupportConfig::current()->whatsapp_number);
        $this->assertNull(SupportConfig::whatsappUrl());
    }

    public function test_invalid_number_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put('/admin/settings/support', ['whatsapp_number' => 'call me'])->assertSessionHasErrors('whatsapp_number');

        $this->assertNull(SupportConfig::current());
    }

    public function test_local_ghana_number_becomes_an_international_wa_me_link(): void
    {
        SupportConfig::create(['whatsapp_number' => '024 123 4567']);
        $this->assertSame('https://wa.me/233241234567', SupportConfig::whatsappUrl());

        SupportConfig::create(['whatsapp_number' => '+233 24 123 4567']);
        $this->assertSame('https://wa.me/233241234567', SupportConfig::whatsappUrl());
    }

    public function test_agent_dashboard_receives_the_whatsapp_link(): void
    {
        SupportConfig::create(['whatsapp_number' => '0241234567']);
        $this->actingAs(Agent::factory()->create(), 'agent');

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page->where('supportWhatsappUrl', 'https://wa.me/233241234567')
        );
    }

    public function test_no_link_is_shared_until_a_number_is_saved(): void
    {
        $this->actingAs(Agent::factory()->create(), 'agent');

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page->where('supportWhatsappUrl', null)
        );
    }
}
