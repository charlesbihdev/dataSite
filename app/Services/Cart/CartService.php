<?php

namespace App\Services\Cart;

use App\Models\Agent;
use App\Models\Subagent;
use App\Services\Pricing\PriceQuote;
use App\Support\GhanaMobileNetwork;
use Illuminate\Support\Str;

/**
 * The agent's Place-Order basket, held in the session (survives refresh for the session
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
    public function add(Agent|Subagent $seller, string $phone, int $sizeGb): ?string
    {
        $phone = GhanaMobileNetwork::normalize($phone);
        if ($phone === '') {
            return 'Enter a valid 10-digit phone number.';
        }

        if ($sizeGb < 1) {
            return 'Enter a bundle size of at least 1 GB.';
        }

        if (($validationError = GhanaMobileNetwork::validateOrder($phone, $sizeGb)) !== null) {
            return $validationError;
        }

        $items = $this->items();
        foreach ($items as $item) {
            if ($item['beneficiary_phone'] === $phone) {
                return "{$phone} is already in your cart. Remove it first to change the bundle.";
            }
        }

        $network = GhanaMobileNetwork::detect($phone);
        $price = $network !== null ? $this->quote->for($seller, $network, $sizeGb) : null;
        if ($network === null || $price === null) {
            return 'No active pricing is configured for this network and size.';
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
     * Add many lines (bulk paste / file upload). Silently skips invalid or duplicate lines.
     *
     * @param  iterable<array{0: string, 1: int|string|float}>  $rows  [phone, sizeGb] pairs
     * @return array{added: int, skipped: int}
     */
    public function addMany(Agent|Subagent $seller, iterable $rows): array
    {
        $added = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $phone = trim((string) ($row[0] ?? ''));
            $sizeGb = (int) ($row[1] ?? 0);

            if ($phone === '' || $this->add($seller, $phone, $sizeGb) !== null) {
                $skipped++;

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
