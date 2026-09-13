<?php

namespace App\Services\Cart;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Agent;
use App\Models\Subagent;
use App\Services\Orders\NewOrderData;
use App\Services\Orders\OrderDispatchService;
use App\Services\Pricing\PriceQuote;

/**
 * Turns the session cart into real orders. Each line is re-priced (never trusting the cart's
 * display cost) and pushed through OrderDispatchService — the one money-safe path that debits the
 * wallet, records earnings, and calls Databundleshub (ARCHITECTURE §3). Balance is checked against
 * the whole batch up front so a short wallet rejects before any order is placed, mirroring the DBH
 * cart checkout. Successfully placed lines are removed from the cart; the rest stay for a retry.
 */
class CartCheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PriceQuote $quote,
        private readonly OrderDispatchService $dispatch,
    ) {}

    /**
     * @return array{placed: int, failed: int, error: ?string}
     */
    public function checkout(Agent|Subagent $seller): array
    {
        $items = $this->cart->items();
        if ($items === []) {
            return ['placed' => 0, 'failed' => 0, 'error' => 'Your cart is empty.'];
        }

        $priced = [];
        $total = 0.0;
        foreach ($items as $item) {
            $price = $this->quote->for($seller, $item['network'], (int) $item['capacity_gb']);
            if ($price === null) {
                continue; // unpriceable now (tier changed) — left in cart, reported as failed below
            }
            $priced[] = ['item' => $item, 'price' => $price];
            $total += $price['amount'];
        }

        if ($priced === []) {
            return ['placed' => 0, 'failed' => count($items), 'error' => 'No cart lines could be priced.'];
        }

        if ((float) $seller->walletOrCreate()->balance < $total) {
            return ['placed' => 0, 'failed' => count($items), 'error' => 'Insufficient wallet balance for this cart.'];
        }

        $placed = 0;
        foreach ($priced as $line) {
            try {
                $this->dispatch->dispatch($this->orderData($seller, $line['item'], $line['price']));
                $this->cart->remove($line['item']['id']);
                $placed++;
            } catch (InsufficientBalanceException) {
                break; // wallet drained mid-batch (concurrent spend) — stop; unplaced lines remain
            }
        }

        return ['placed' => $placed, 'failed' => count($items) - $placed, 'error' => null];
    }

    /**
     * @param  array{id: string, beneficiary_phone: string, network: string, capacity_gb: int}  $item
     * @param  array{pricePerGb: float, amount: float, baseCost: float}  $price
     */
    private function orderData(Agent|Subagent $seller, array $item, array $price): NewOrderData
    {
        // Same cascade as the Developer API: the seller buys at their own tier rate, so
        // customer_price = seller_cost; the platform still profits on agent_cost − base_cost.
        $agentCost = $seller instanceof Subagent
            ? ($this->quote->for($seller->agent, $item['network'], (int) $item['capacity_gb'])['amount'] ?? $price['amount'])
            : $price['amount'];

        return new NewOrderData(
            seller: $seller,
            network: $item['network'],
            capacityGb: (int) $item['capacity_gb'],
            beneficiaryPhone: $item['beneficiary_phone'],
            customerPrice: $price['amount'],
            sellerCost: $price['amount'],
            agentCost: $agentCost,
            baseCost: $price['baseCost'],
            source: 'portal',
            idempotencyKey: $item['id'],
        );
    }
}
