<?php

namespace Tests\Feature;

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\Tools\LookupApiDocumentation;
use App\Models\Agent;
use App\Models\Subagent;
use App\Services\Storefront\StoreAssistant\AssistantContext;
use App\Services\Storefront\StoreAssistant\StoreAssistantService;
use App\Support\GhanaMobileNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\TestCase;

/**
 * The storefront AI assistant: a scoped, store-public helper that answers buying questions, refuses
 * off-topic prompts with one fixed line, and never leaks the platform or a path up the reseller ladder.
 */
class StoreAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function agentWithStore(array $overrides = []): Agent
    {
        $agent = Agent::factory()->create(array_merge([
            'slug' => 'kofi-data', 'store_name' => 'Kofi Data',
            'is_active' => true, 'store_active' => true, 'whatsapp_number' => '0241234567',
        ], $overrides));
        $agent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 5, 'cost_price' => 20, 'selling_price' => 24, 'is_active' => true]);

        return $agent;
    }

    public function test_it_answers_an_on_topic_question(): void
    {
        $this->agentWithStore();
        StorefrontAssistant::fake(['Tap Buy Now, enter the number, and pay with Mobile Money.']);

        $this->postJson(route('agent.storefront.assistant', ['agentSlug' => 'kofi-data']), [
            'message' => 'How do I buy data?',
        ])
            ->assertOk()
            ->assertJson(['reply' => 'Tap Buy Now, enter the number, and pay with Mobile Money.']);

        StorefrontAssistant::assertPrompted('How do I buy data?');
    }

    public function test_off_topic_prompts_get_the_fixed_canned_reply(): void
    {
        $this->agentWithStore();
        // The model emits the sentinel for anything out of scope; the service swaps it for one fixed line.
        StorefrontAssistant::fake([StorefrontAssistant::OFF_TOPIC]);

        $this->postJson(route('agent.storefront.assistant', ['agentSlug' => 'kofi-data']), [
            'message' => 'Solve this calculus problem: integrate x^2 dx',
        ])
            ->assertOk()
            ->assertJson(['reply' => StoreAssistantService::OFF_TOPIC_REPLY]);
    }

    public function test_it_requires_a_message(): void
    {
        $this->agentWithStore();
        StorefrontAssistant::fake();

        $this->postJson(route('agent.storefront.assistant', ['agentSlug' => 'kofi-data']), [
            'message' => '',
        ])->assertJsonValidationErrorFor('message');

        StorefrontAssistant::assertNeverPrompted();
    }

    public function test_history_is_rejected_when_too_large(): void
    {
        $this->agentWithStore();
        StorefrontAssistant::fake();

        $history = array_fill(0, 31, ['role' => 'user', 'content' => 'hi']);

        $this->postJson(route('agent.storefront.assistant', ['agentSlug' => 'kofi-data']), [
            'message' => 'How do I pay?',
            'history' => $history,
        ])->assertJsonValidationErrorFor('history');
    }

    public function test_a_disabled_store_has_no_assistant(): void
    {
        $this->agentWithStore(['store_active' => false]);
        StorefrontAssistant::fake();

        $this->postJson(route('agent.storefront.assistant', ['agentSlug' => 'kofi-data']), [
            'message' => 'How do I buy data?',
        ])->assertNotFound();
    }

    public function test_the_subagent_storefront_has_its_own_assistant(): void
    {
        $agent = Agent::factory()->create();
        $subagent = Subagent::create([
            'agent_id' => $agent->id, 'name' => 'Kwame', 'phone' => '0247000001',
            'email' => 'kwame@ex.com', 'username' => 'kwame', 'slug' => 'kwame-data',
            'store_name' => 'Kwame Data', 'password' => 'secret', 'is_active' => true, 'store_active' => true,
        ]);
        $subagent->packagePrices()->create(['network' => 'mtn', 'capacity_gb' => 5, 'cost_price' => 20, 'selling_price' => 22, 'is_active' => true]);

        StorefrontAssistant::fake(['You can top up any Ghana number at checkout.']);

        $this->postJson(route('subagent.storefront.assistant', ['subagentSlug' => 'kwame-data']), [
            'message' => 'Can I buy for another number?',
        ])
            ->assertOk()
            ->assertJson(['reply' => 'You can top up any Ghana number at checkout.']);
    }

    public function test_the_instructions_expose_store_facts_but_not_the_platform_or_ladder(): void
    {
        $instructions = (new StorefrontAssistant($this->context()))->instructions();

        // Store-public facts are present…
        $this->assertStringContainsString('Kofi Data', $instructions);
        $this->assertStringContainsString('5GB', $instructions);

        // …but the firewall topics are forbidden by the prompt (never offered as something to reveal).
        $this->assertStringContainsString('Never', $instructions);
        $this->assertStringContainsString('sub-agent', $instructions);
        $this->assertStringContainsString('URL', $instructions);
    }

    public function test_the_instructions_describe_the_ordering_process(): void
    {
        $instructions = (new StorefrontAssistant($this->context()))->instructions();

        $this->assertStringContainsString('Mobile Money', $instructions);
        $this->assertStringContainsString('Track order', $instructions);
        $this->assertStringContainsString('MTN, Telecel, and AT', $instructions);
        $this->assertStringContainsString('awaiting payment', $instructions);
    }

    public function test_the_agent_exposes_the_api_docs_tool_but_keeps_docs_out_of_the_base_prompt(): void
    {
        $agent = new StorefrontAssistant($this->context());

        // The model fetches API docs via the tool, so the raw reference is NOT dumped into the prompt.
        $instructions = $agent->instructions();
        $this->assertStringNotContainsString('X-API-Key', $instructions);
        $this->assertStringContainsString('lookup_api_documentation', $instructions);

        $tools = iterator_to_array($agent->tools());
        $this->assertCount(1, $tools);
        $this->assertInstanceOf(LookupApiDocumentation::class, $tools[0]);
        $this->assertSame('lookup_api_documentation', $tools[0]->name());
    }

    public function test_the_api_reference_tool_returns_real_endpoints_and_every_networks_bundles(): void
    {
        $docs = (string) (new LookupApiDocumentation)->handle(new ToolRequest);

        // Endpoints + auth are documented…
        $this->assertStringContainsString('X-API-Key', $docs);
        $this->assertStringContainsString('POST /api/create_order', $docs);
        $this->assertStringContainsString('GET /api/data-packages', $docs);

        // …and the real per-network bundle sizes are present (not lazily omitted), incl. Telecel's set.
        foreach (GhanaMobileNetwork::meta() as $network) {
            foreach ($network['sizes'] as $size) {
                $this->assertStringContainsString((string) $size, $docs);
            }
        }
        $this->assertStringContainsString('Telecel', $docs);
        $this->assertStringContainsString('100 GB', $docs); // Telecel/AT top bundle

        // Firewall still holds inside the reference — no concrete host.
        $this->assertStringContainsString('<your-api-host>', $docs);
    }

    public function test_a_developer_api_question_still_returns_a_reply(): void
    {
        $this->agentWithStore();
        StorefrontAssistant::fake(['Send a POST to /api/create_order with your X-API-Key header.']);

        $this->postJson(route('agent.storefront.assistant', ['agentSlug' => 'kofi-data']), [
            'message' => 'How do I use your API to place an order?',
        ])
            ->assertOk()
            ->assertJson(['reply' => 'Send a POST to /api/create_order with your X-API-Key header.']);
    }

    private function context(): AssistantContext
    {
        return AssistantContext::fromStore(
            ['name' => 'Kofi Data', 'whatsapp' => '0241234567', 'whatsappGroup' => null],
            [['network' => 'mtn', 'networkLabel' => 'MTN', 'capacityGb' => 5, 'price' => 24.0]],
        );
    }
}
