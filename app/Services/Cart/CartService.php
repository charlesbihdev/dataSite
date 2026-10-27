<?php

namespace App\Services\Cart;

use App\Models\Agent;
use App\Models\Subagent;
use App\Services\Pricing\PriceQuote;
use App\Support\GhanaMobileNetwork;
use Illuminate\Support\Str;

/**
 * The agent's / subagent's Place-Order basket, held in the session (survives refresh for the session
 * lifetime). Each of the dashboard's three inputs — single, bulk paste, file upload — funnels
 * through add()/addMany(); checkout later re-prices and dispatches every line. Prices are only
 * indicative here; OrderDispatchService re-resolves the authoritative cascade at checkout.
 */
class CartService
{
    private const SESSION_KEY = 'agent_cart';

    public function __construct(private readonly PriceQuote $quote) {}

    /**
     * @return list<array{id: string, beneficiary_phone: string, network: string, network_label: string, capacity_gb: int, bundle: string, cost: float}>
     */
    public function items(): array
    {
        $cart = session(self::SESSION_KEY, []);

        return is_array($cart) ? array_values($cart) : [];
    }

    public function total(): float
    {
        return round(array_reduce(
            $this->items(),
            static fn (float $carry, array $item): float => $carry + (float) $item['cost'],
            0.0,
        ), 2);
    }

    public function count(): int
    {
        return count($this->items());
    }

    /**
     * Validate, price, and append one line. Returns a human error message, or null on success.
     */
    public function add(Agent|Subagent $seller, string $phone, int $sizeGb, ?string $network = null): ?string
    {
        $phone = GhanaMobileNetwork::normalize($phone);
        if ($phone === '') {
            return 'Enter a valid 10-digit phone number.';
        }

        if ($sizeGb < 1) {
            return 'Enter a bundle size of at least 1 GB.';
        }

        // Network column: a recognized code overrides prefix detection; blank OR unrecognized (typo)
        // falls back to auto-detecting from the phone.
        $network = ($network !== null && $network !== '' ? GhanaMobileNetwork::normalizeCode($network) : null)
            ?? GhanaMobileNetwork::detect($phone);

        // A subagent can only buy what their agent opened, so check that before the supplier's size
        // list — otherwise the error would advertise sizes they still can't buy.
        if ($seller instanceof Subagent && $network !== null && ($label = GhanaMobileNetwork::label($network)) !== null) {
            $opened = $this->quote->subagentSizes($seller, $network);
            if (! in_array($sizeGb, $opened, true)) {
                return $opened === []
                    ? "Your agent hasn't opened any {$label} bundles yet."
                    : "Not offered by your agent. Your {$label} bundles: ".implode(', ', $opened).' GB.';
            }
        }

        if (($validationError = GhanaMobileNetwork::validateOrder($phone, $sizeGb, $network)) !== null) {
            return $validationError;
        }

        // Repeats of the same number are allowed (e.g. several bundles to one line); the UI flags
        // duplicates in red so an accidental one is noticed before checkout.
        $items = $this->items();

        $price = $network !== null ? $this->quote->for($seller, $network, $sizeGb) : null;
        if ($network === null || $price === null) {
            return $seller instanceof Subagent
                ? 'Your agent has not opened this bundle to sub-agents yet.'
                : 'No active pricing is configured for this network and size.';
        }

        $items[] = [
            'id' => (string) Str::uuid(),
            'beneficiary_phone' => $phone,
            'network' => $network,
            'network_label' => (string) GhanaMobileNetwork::label($network),
            'capacity_gb' => $sizeGb,
            'bundle' => $sizeGb.'GB',
            'cost' => $price['amount'],
        ];
        $this->put($items);

        return null;
    }

    /**
     * Add many lines (bulk paste / file upload). Invalid lines are skipped — never added — and each is
     * reported back with its row number and the reason, so the seller can fix and re-add it.
     * Repeats are kept.
     *
     * @param  iterable<array{0: string, 1: int|string|float, 2?: string, 3?: int}>  $rows  [phone, sizeGb, network?, rowNumber?]
     * @return array{added: int, skipped: list<array{row: int, phone: string, size: string, reason: string}>}
     */
    public function addMany(Agent|Subagent $seller, iterable $rows): array
    {
        $added = 0;
        $skipped = [];

        foreach ($rows as $index => $row) {
            $phone = trim((string) ($row[0] ?? ''));
            $size = trim((string) ($row[1] ?? ''));
            $network = trim((string) ($row[2] ?? ''));

            $error = $phone === ''
                ? 'Missing phone number.'
                : $this->add($seller, $phone, (int) $size, $network);

            if ($error !== null) {
                $skipped[] = ['row' => (int) ($row[3] ?? $index + 1), 'phone' => $phone, 'size' => $size, 'reason' => $error];

                continue;
            }
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    public function remove(string $id): void
    {
        $this->put(array_filter($this->items(), static fn (array $item): bool => $item['id'] !== $id));
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function put(array $items): void
    {
        session([self::SESSION_KEY => array_values($items)]);
    }
}
