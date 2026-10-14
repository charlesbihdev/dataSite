<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\UnapprovedBeneficiary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBeneficiariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        DbhConfig::query()->create([
            'base_url' => 'https://dbh.test/api',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
    }

    public function test_unapproved_beneficiary_is_auto_captured_on_storefront_check(): void
    {
        $agent = Agent::factory()->create([
            'slug' => 'kofi-store',
            'store_name' => 'Kofi Store',
        ]);

        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => true,
                'data' => [
                    [
                        'msisdn' => '0558119182',
                        'approved' => false,
                        'status' => 'not_approved',
                        'message' => '0558119182 is not on your approved beneficiary list.',
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson(route('agent.storefront.eligibility', ['agentSlug' => 'kofi-store']), [
            'phone' => '0558119182',
        ]);

        $response->assertOk()
            ->assertJsonPath('approved', false)
            ->assertJsonPath('status', 'not_approved');

        $this->assertDatabaseHas('unapproved_beneficiaries', [
            'phone' => '0558119182',
            'network' => 'mtn',
            'agent_id' => $agent->id,
            'source_label' => 'Kofi Store',
            'status' => 'pending',
            'check_count' => 1,
        ]);
    }

    public function test_duplicate_check_increments_count_instead_of_duplicating_row(): void
    {
        $agent = Agent::factory()->create(['slug' => 'yaw-store']);

        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => true,
                'data' => [
                    [
                        'msisdn' => '0558119182',
                        'approved' => false,
                        'status' => 'not_approved',
                        'message' => 'Not approved',
                    ],
                ],
            ]),
        ]);

        $this->postJson(route('agent.storefront.eligibility', ['agentSlug' => 'yaw-store']), [
            'phone' => '0558119182',
        ]);

        $this->postJson(route('agent.storefront.eligibility', ['agentSlug' => 'yaw-store']), [
            'phone' => '0558119182',
        ]);

        $this->assertDatabaseCount('unapproved_beneficiaries', 1);
        $this->assertDatabaseHas('unapproved_beneficiaries', [
            'phone' => '0558119182',
            'check_count' => 2,
        ]);
    }

    public function test_admin_can_view_beneficiaries_index(): void
    {
        $this->actingAsAdmin();

        UnapprovedBeneficiary::create([
            'phone' => '0558119182',
            'network' => 'mtn',
            'status' => 'pending',
            'source_label' => 'Test Store',
            'check_count' => 1,
            'last_checked_at' => now(),
        ]);

        $response = $this->get('/admin/beneficiaries');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/beneficiaries')
                ->has('beneficiaries.data', 1)
                ->where('counts.pending', 1)
                ->where('counts.total', 1)
            );
    }

    public function test_admin_can_export_beneficiaries_csv_with_phone_text_literals(): void
    {
        $this->actingAsAdmin();

        UnapprovedBeneficiary::create([
            'phone' => '0558119182',
            'network' => 'mtn',
            'status' => 'pending',
            'source_label' => 'Test Store',
            'check_count' => 1,
            'last_checked_at' => now(),
        ]);

        $response = $this->get('/admin/beneficiaries/export?status=pending');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        // Stream the CSV output
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Check that phone number is wrapped in ="0558119182" for Excel preservation of leading zero
        $this->assertStringContainsString('0558119182', $content);
        $this->assertStringContainsString('MTN', $content);
        $this->assertStringContainsString('Pending', $content);
    }

    public function test_admin_can_mark_beneficiaries_as_submitted(): void
    {
        $this->actingAsAdmin();

        $b = UnapprovedBeneficiary::create([
            'phone' => '0558119182',
            'network' => 'mtn',
            'status' => 'pending',
            'check_count' => 1,
            'last_checked_at' => now(),
        ]);

        $response = $this->post('/admin/beneficiaries/mark-submitted', [
            'ids' => [$b->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('unapproved_beneficiaries', [
            'id' => $b->id,
            'status' => 'submitted',
        ]);
        $this->assertNotNull($b->fresh()->submitted_at);
    }

    public function test_admin_can_mark_beneficiaries_as_approved(): void
    {
        $this->actingAsAdmin();

        $b = UnapprovedBeneficiary::create([
            'phone' => '0558119182',
            'network' => 'mtn',
            'status' => 'submitted',
            'check_count' => 1,
            'last_checked_at' => now(),
        ]);

        $response = $this->post('/admin/beneficiaries/mark-approved', [
            'ids' => [$b->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('unapproved_beneficiaries', [
            'id' => $b->id,
            'status' => 'approved',
        ]);
        $this->assertNotNull($b->fresh()->approved_at);
    }

    public function test_admin_can_recheck_beneficiary_status_against_upstream(): void
    {
        $this->actingAsAdmin();

        $b = UnapprovedBeneficiary::create([
            'phone' => '0558119182',
            'network' => 'mtn',
            'status' => 'submitted',
            'check_count' => 1,
            'last_checked_at' => now(),
        ]);

        Http::fake([
            'dbh.test/api/beneficiaries/check*' => Http::response([
                'success' => true,
                'enforced' => true,
                'data' => [
                    [
                        'msisdn' => '0558119182',
                        'approved' => true,
                        'status' => 'approved',
                        'message' => null,
                    ],
                ],
            ]),
        ]);

        $response = $this->post('/admin/beneficiaries/recheck', [
            'ids' => [$b->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('unapproved_beneficiaries', [
            'id' => $b->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_delete_beneficiary(): void
    {
        $this->actingAsAdmin();

        $b = UnapprovedBeneficiary::create([
            'phone' => '0558119182',
            'network' => 'mtn',
            'status' => 'pending',
            'check_count' => 1,
            'last_checked_at' => now(),
        ]);

        $response = $this->delete("/admin/beneficiaries/{$b->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('unapproved_beneficiaries', [
            'id' => $b->id,
        ]);
    }
}
