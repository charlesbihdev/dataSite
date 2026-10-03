<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AssistantChatRequest;
use App\Models\Agent;
use App\Models\Subagent;
use App\Services\Storefront\StoreAssistant\AssistantContext;
use App\Services\Storefront\StoreAssistant\StoreAssistantService;
use App\Support\GhanaMobileNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * The storefront AI assistant endpoint, shared by the D2 agent shop and the D3 subagent shop. It takes
 * one chat turn (message + short history), builds the store's public-safe {@see AssistantContext}, and
 * returns the assistant's reply as JSON. No auth — the shop is public; the slug + active checks scope it.
 */
class StoreAssistantController extends Controller
{
    public function __construct(private readonly StoreAssistantService $assistant) {}

    public function agent(AssistantChatRequest $request, string $agentSlug): JsonResponse
    {
        $store = Agent::query()->where('slug', $agentSlug)->firstOrFail();

        return $this->respond($store, $request);
    }

    public function subagent(AssistantChatRequest $request, string $subagentSlug): JsonResponse
    {
        $store = Subagent::query()
            ->where('slug', $subagentSlug)
            ->orWhere('username', $subagentSlug)
            ->firstOrFail();

        return $this->respond($store, $request);
    }

    private function respond(Model $store, AssistantChatRequest $request): JsonResponse
    {
        abort_unless($store->is_active && $store->store_active, 404);

        $result = $this->assistant->reply(
            $this->contextFor($store),
            (string) $request->string('message'),
            $request->history(),
        );

        return response()->json($result);
    }

    /** Agent and Subagent expose the same store/package accessors, so one builder serves both. */
    private function contextFor(Model $store): AssistantContext
    {
        $packages = $store->packagePrices()
            ->where('is_active', true)
            ->orderBy('network')
            ->orderBy('capacity_gb')
            ->get()
            ->map(fn ($p): array => [
                'network' => $p->network,
                'networkLabel' => GhanaMobileNetwork::label($p->network),
                'capacityGb' => $p->capacity_gb,
                'price' => (float) $p->selling_price,
            ])
            ->all();

        return AssistantContext::fromStore([
            'name' => $store->store_name ?: $store->name,
            'whatsapp' => $store->whatsapp_number,
            'whatsappGroup' => $store->whatsapp_group_link,
        ], $packages);
    }
}
