<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\BaseCost;
use App\Models\Earning;
use App\Models\Order;
use App\Models\PricingTier;
use App\Models\Subagent;
use App\Models\TierPrice;
use App\Services\Orders\ProfitSplit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dev-only demo data so the admin backoffice shows real numbers. Creates a tier, one agent
 * and one subagent with funded wallets, and a spread of orders (completed / processing /
 * failed) with the frozen cascade + settled earnings. Never runs in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoDataSeeder skipped in production.');

            return;
        }

        Admin::firstOrCreate(
            ['email' => 'bihcharles2004@gmail.com'],
            ['name' => 'Super Admin', 'phone' => '0548715098', 'username' => 'superadmin', 'password' => '@Charles2004', 'is_active' => true],
        );

        $tier = PricingTier::firstOrCreate(['name' => 'Standard'], ['is_active' => true]);

        // Base cost (what we pay Databundleshub) + this tier's selling rates, per network band.
        foreach ([['mtn', 1, 10, 4.0, 6.0], ['mtn', 11, 50, 3.5, 5.0], ['telecel', 1, 20, 3.8, 5.5], ['at', 1, 20, 3.2, 4.8]] as [$net, $min, $max, $cost, $sell]) {
            BaseCost::firstOrCreate(
                ['network' => $net, 'min_gb' => $min, 'max_gb' => $max],
                ['cost_per_gb' => $cost, 'is_active' => true],
            );
            TierPrice::firstOrCreate(
                ['pricing_tier_id' => $tier->id, 'network' => $net, 'min_gb' => $min, 'max_gb' => $max],
                ['price_per_gb' => $sell, 'is_active' => true],
            );
        }

        $agent = Agent::firstOrCreate(
            ['phone' => '0551000001'],
            [
                'name' => 'Agent Mensah',
                'email' => 'bihcharles2004@gmail.com',
                'username' => 'agent',
                'password' => '@Charles2004',
                'pricing_tier_id' => $tier->id,
                'is_active' => true,
            ],
        );
        $subagent = Subagent::firstOrCreate(
            ['phone' => '0548715098'],
            [
                'name' => 'Subagent Asare',
                'email' => 'bihcharles2004@gmail.com',
                'username' => 'kofi',
                'password' => '@Charles2004',
                'agent_id' => $agent->id,
                'is_active' => true,
            ],
        );

        foreach ([$agent, $subagent] as $owner) {
            $wallet = $owner->walletOrCreate();
            if ((float) $wallet->balance < 100) {
                $wallet->credit(500, 'topup', null, 'Demo funding');
            }
        }

        $profitSplit = new ProfitSplit;

        // network, gb, customer, seller, agentCost, base, upstream, status, seller, daysAgo
        $rows = [
            ['mtn', 5, 30, 25, 20, 15, 15, Order::STATUS_COMPLETED, $subagent, 1],
            ['mtn', 10, 55, 48, 40, 30, 30, Order::STATUS_COMPLETED, $subagent, 2],
            ['telecel', 3, 18, 18, 15, 12, 12, Order::STATUS_COMPLETED, $agent, 2],
            ['at', 2, 12, 12, 10, 8, 8, Order::STATUS_COMPLETED, $agent, 3],
            ['mtn', 20, 100, 90, 78, 60, null, Order::STATUS_PROCESSING, $subagent, 0],
            ['mtn', 1, 8, 7, 6, 5, null, Order::STATUS_FAILED, $agent, 0],
        ];

        foreach ($rows as [$network, $gb, $customer, $seller, $agentCost, $base, $upstream, $status, $owner, $daysAgo]) {
            /** @var Order $order */
            $order = $owner->orders()->create([
                'reference' => 'DS-'.strtoupper(Str::random(10)),
                'network' => $network,
                'capacity_gb' => $gb,
                'beneficiary_phone' => '0209'.random_int(100000, 999999),
                'channel' => Order::CHANNEL_PREPAID,
                'customer_price' => $customer,
                'seller_cost' => $seller,
                'agent_cost' => $agentCost,
                'base_cost' => $base,
                'upstream_cost' => $upstream,
                'status' => $status,
                'completed_at' => $status === Order::STATUS_COMPLETED ? now()->subDays($daysAgo) : null,
                'failed_at' => $status === Order::STATUS_FAILED ? now() : null,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);

            $earningStatus = match ($status) {
                Order::STATUS_COMPLETED => Earning::STATUS_CREDITED,
                Order::STATUS_FAILED => Earning::STATUS_REVERSED,
                default => Earning::STATUS_PENDING,
            };

            foreach ($profitSplit->for($order) as $share) {
                $share['earner']->earnings()->create([
                    'order_id' => $order->id,
                    'type' => $share['type'],
                    'amount' => $share['amount'],
                    'status' => $earningStatus,
                    'credited_at' => $earningStatus === Earning::STATUS_CREDITED ? now()->subDays($daysAgo) : null,
                ]);
            }
        }

        // A couple of withdrawal requests against the credited earnings.
        if ($agent->withdrawals()->count() === 0) {
            $agent->withdrawals()->create(['amount' => 5, 'status' => 'pending', 'reference' => 'WD-'.strtoupper(Str::random(6))]);
            $subagent->withdrawals()->create(['amount' => 8, 'status' => 'approved', 'reference' => 'WD-'.strtoupper(Str::random(6))]);
        }

        $this->command?->info('Demo data seeded: 1 agent, 1 subagent, '.count($rows).' orders.');
    }
}
