<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SchedulerHealthTest extends TestCase
{
    public function test_reports_ok_when_the_heartbeat_is_fresh(): void
    {
        Cache::put('scheduler:last-run', now()->toIso8601String(), now()->addDay());

        $this->getJson('/up/scheduler')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_reports_503_when_the_heartbeat_is_missing(): void
    {
        Cache::forget('scheduler:last-run');

        $this->getJson('/up/scheduler')
            ->assertStatus(503)
            ->assertJson(['ok' => false, 'last_run' => null]);
    }

    public function test_reports_503_when_the_heartbeat_is_stale(): void
    {
        Cache::put('scheduler:last-run', now()->subMinutes(10)->toIso8601String(), now()->addDay());

        $this->getJson('/up/scheduler')->assertStatus(503)->assertJson(['ok' => false]);
    }
}
