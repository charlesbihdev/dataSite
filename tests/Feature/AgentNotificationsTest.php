<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Services\Notifications\AgentNotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function announce(string $title): Announcement
    {
        return Announcement::create(['title' => $title, 'body' => "Body of {$title}"]);
    }

    public function test_admin_sends_a_notification(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/notifications', ['title' => 'Maintenance', 'body' => 'Back at 6pm.'])->assertRedirect();

        $this->assertSame(1, Announcement::where('title', 'Maintenance')->count());
        $this->get('/admin/notifications')->assertInertia(
            fn (Assert $page) => $page->component('admin/notifications')->where('sent.data.0.title', 'Maintenance')
        );
    }

    public function test_title_and_message_are_required(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/notifications', ['title' => '', 'body' => ''])->assertSessionHasErrors(['title', 'body']);
    }

    public function test_admin_deletes_a_notification(): void
    {
        $this->actingAsAdmin();
        $n = $this->announce('Oops');

        $this->delete("/admin/notifications/{$n->id}")->assertRedirect();

        $this->assertNull(Announcement::find($n->id));
    }

    public function test_agent_sees_unread_count_and_newest_first(): void
    {
        $this->announce('Older');
        $this->announce('Newer');
        $this->actingAs(Agent::factory()->create(), 'agent');

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->where('notifications.unread', 2)
                ->where('notifications.items.0.title', 'Newer')
                ->where('notifications.items.0.read', false)
        );
    }

    public function test_list_is_limited_to_the_latest_few(): void
    {
        foreach (range(1, AgentNotificationFeed::LIST_SIZE + 3) as $i) {
            $this->announce("N{$i}");
        }
        $this->actingAs(Agent::factory()->create(), 'agent');

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->has('notifications.items', AgentNotificationFeed::LIST_SIZE)
                ->where('notifications.unread', AgentNotificationFeed::LIST_SIZE + 3)
        );
    }

    public function test_marking_one_read_drops_the_count_for_that_agent_only(): void
    {
        $n = $this->announce('Hello');
        $other = Agent::factory()->create();
        $agent = Agent::factory()->create();
        $this->actingAs($agent, 'agent');

        $this->post(route('agent.notifications.read', $n))->assertRedirect();
        $this->post(route('agent.notifications.read', $n))->assertRedirect(); // repeat is harmless

        $this->assertSame(1, AnnouncementRead::count());
        $feed = app(AgentNotificationFeed::class);
        $this->assertSame(0, $feed->for($agent)['unread']);
        $this->assertTrue($feed->for($agent)['items'][0]['read']);
        $this->assertSame(1, $feed->for($other)['unread']);
    }

    public function test_mark_all_read_clears_the_badge(): void
    {
        $this->announce('A');
        $this->announce('B');
        $agent = Agent::factory()->create();
        $this->actingAs($agent, 'agent');

        $this->post(route('agent.notifications.read-all'))->assertRedirect();

        $this->assertSame(0, app(AgentNotificationFeed::class)->for($agent)['unread']);
    }

    public function test_new_agent_sees_notifications_sent_before_they_joined(): void
    {
        $this->announce('Welcome pack');
        $this->actingAs(Agent::factory()->create(), 'agent');

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page->where('notifications.items.0.title', 'Welcome pack')
        );
    }

    public function test_admin_pages_get_no_agent_feed(): void
    {
        $this->announce('Agents only');
        $this->actingAsAdmin();

        $this->get('/admin/notifications')->assertInertia(fn (Assert $page) => $page->where('notifications', null));
    }
}
