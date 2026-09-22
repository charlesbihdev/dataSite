<?php

namespace Tests\Feature;

use App\Models\DbhConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /** A base URL + key that Databundleshub accepts (both verification calls succeed). */
    private function fakeHealthyConnection(): void
    {
        Http::fake([
            '*/get_pricing' => Http::response(['success' => true, 'data' => []], 200),
            '*/developer/data-packages' => Http::response(['success' => true, 'data' => []], 200),
        ]);
    }

    public function test_saves_a_new_connection(): void
    {
        $this->fakeHealthyConnection();

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

    public function test_base_url_is_stored_as_entered_apart_from_a_trailing_slash(): void
    {
        $this->fakeHealthyConnection();

        $this->put('/admin/settings/connection', [
            'base_url' => 'https://dbh.test/api/',
            'api_key' => 'live-key',
            'is_active' => true,
        ])->assertRedirect();

        // No supplier-specific munging — we keep exactly what was entered (bar the trailing slash),
        // so the base URL stays fully configurable if Databundleshub's URL ever changes.
        $this->assertSame('https://dbh.test/api', DbhConfig::query()->first()->base_url);
    }

    public function test_blank_key_keeps_the_existing_one(): void
    {
        $this->fakeHealthyConnection();
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

    public function test_save_is_blocked_when_the_key_is_rejected(): void
    {
        Http::fake([
            '*/get_pricing' => Http::response(['success' => true, 'data' => []], 200),
            '*/developer/data-packages' => Http::response(['success' => false, 'message' => 'Unauthorized'], 401),
        ]);

        $this->put('/admin/settings/connection', [
            'base_url' => 'https://dbh.test/api',
            'api_key' => 'wrong-key',
            'is_active' => true,
        ])->assertSessionHasErrors('base_url');

        $this->assertNull(DbhConfig::query()->first()); // nothing stored
    }
}
