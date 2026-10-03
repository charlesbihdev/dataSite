<?php

namespace App\Services\Storefront\StoreAssistant;

use App\Support\GhanaMobileNetwork;

/**
 * The PUBLIC-SAFE snapshot of a single storefront that the AI assistant is allowed to know about.
 *
 * This is the privacy firewall in data form: it carries ONLY what a walk-in customer may already see
 * on the shop (store display name, the bundles on sale, support contact). It deliberately excludes the
 * platform name, any domain/URL, the agent/sub-agent tier model, and anything about "becoming a
 * reseller" — so the assistant can never leak a path for a customer to climb the ladder.
 */
final class AssistantContext
{
    /**
     * @param  list<array{network: string, networkLabel: string|null, capacityGb: int|float, price: float}>  $packages
     */
    public function __construct(
        public readonly string $storeName,
        public readonly ?string $whatsapp,
        public readonly bool $hasWhatsappGroup,
        public readonly array $packages,
    ) {}

    /**
     * Build from the shaped arrays the storefront controllers already compute, so the assistant and the
     * shop always describe the same store and the same live bundles.
     *
     * @param  array{name: string, whatsapp: string|null, whatsappGroup: string|null}  $store
     * @param  list<array{network: string, networkLabel: string|null, capacityGb: int|float, price: float}>  $packages
     */
    public static function fromStore(array $store, array $packages): self
    {
        return new self(
            storeName: $store['name'],
            whatsapp: $store['whatsapp'] ?? null,
            hasWhatsappGroup: ! empty($store['whatsappGroup']),
            packages: $packages,
        );
    }

    /**
     * A compact, model-friendly description of what this store sells and how, used inside the agent's
     * instructions. Contains store-public facts only — never the platform, a URL, or any tier wording.
     */
    public function toPromptBlock(): string
    {
        $lines = ["Store name: {$this->storeName}"];

        if ($this->packages === []) {
            $lines[] = 'Bundles on sale: none are listed right now.';
        } else {
            $lines[] = 'Bundles on sale (network · size · price in Ghana Cedis):';
            foreach ($this->groupedByNetwork() as $label => $rows) {
                $items = implode(', ', array_map(
                    static fn (array $p): string => "{$p['capacityGb']}GB = GH₵".number_format($p['price'], 2),
                    $rows,
                ));
                $lines[] = "- {$label}: {$items}";
            }
        }

        $lines[] = $this->whatsapp !== null && $this->whatsapp !== ''
            ? "Support: customers can reach the store on WhatsApp at {$this->whatsapp}."
            : 'Support: there is no WhatsApp number listed for this store.';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, list<array{network: string, networkLabel: string|null, capacityGb: int|float, price: float}>>
     */
    private function groupedByNetwork(): array
    {
        $grouped = [];

        foreach ($this->packages as $p) {
            $label = $p['networkLabel'] ?? GhanaMobileNetwork::label($p['network']) ?? $p['network'];
            $grouped[$label][] = $p;
        }

        return $grouped;
    }
}
