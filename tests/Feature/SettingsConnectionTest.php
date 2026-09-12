<?php

namespace Tests\Feature;

use App\Models\DbhConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_a_new_connection(): void
    {
        $this->put('/admin/settings/connection', [
            'base_url' => 'https://dbh.test/api',
            'api_key' => 'live-key',
            'is_active' => true,
        ])->assertRedirect();

        $config = DbhConfig::query()->first();
        $this->assertNotNull($config);
        $this->assertSame('https://dbh.test/api', $config->base_url);
        $this->assertSame('live-key', $config->api_key); // decrypted via cast
    }

    public function test_blank_key_keeps_the_existing_one(): void
    {
        DbhConfig::create(['base_url' => 'https://old.test/api', 'api_key' => 'old-key', 'is_active' => true]);

        $this->put('/admin/settings/connection', [
            'base_url' => 'https://new.test/api',
            'api_key' => '',
            'is_active' => true,
        ])->assertRedirect();

        $config = DbhConfig::query()->first();
        $this->assertSame('https://new.test/api', $config->base_url);
        $this->assertSame('old-key', $config->api_key);
    }
}
