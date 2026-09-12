<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\ApiKey;
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
 * and one subagent with funded wallets, api keys, and a spread of orders (completed / processing /
 * failed) with the frozen cascade + settled earnings. Completely idempotent. Never runs in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoDataSeeder skipped in production.');

            return;
        }

        $tier = PricingTier::firstOrCreate(['name' => 'Standard'], ['is_active' => true]);



        $agent = Agent::firstOrCreate(
            ['username' => 'agent'],
            [
                'name' => 'Agent Mensah',
                'phone' => '0551000001',
                'email' => 'agent@datasite.com',
                'password' => '@Charles2004',
                'pricing_tier_id' => $tier->id,
                'is_active' => true,
            ],
        );
        $subagent = Subagent::firstOrCreate(
            ['username' => 'kofi'],
            [
                'name' => 'Subagent Asare',
                'phone' => '0548715098',
                'email' => 'subagent@datasite.com',
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

        // Demo API Keys (idempotent)
        ApiKey::firstOrCreate(
            ['prefix' => 'dsk_demoagent'],
            [
                'owner_type' => $agent->getMorphClass(),
                'owner_id' => $agent->id,
                'name' => 'Agent Production Key',
                'prefix' => 'dsk_demoagent',
                'key_hash' => ApiKey::hash('dsk_demoagent_secret123456789012345678901234567890'),
                'is_active' => true,
            ]
        );

        ApiKey::firstOrCreate(
            ['prefix' => 'dsk_demosub'],
            [
                'owner_type' => $subagent->getMorphClass(),
                'owner_id' => $subagent->id,
                'name' => 'Subagent Integration Key',
                'prefix' => 'dsk_demosub',
                'key_hash' => ApiKey::hash('dsk_demosub_secret123456789012345678901234567890'),
                'is_active' => true,
            ]
        );

        $profitSplit = new ProfitSplit;

        // ref, network, gb, customer, seller, agentCost, base, upstream, status, seller, daysAgo
        $rows = [
            ['DS-DEMO-001', 'mtn', 5, 30, 25, 20, 15, 15, Order::STATUS_COMPLETED, $subagent, 1],
            ['DS-DEMO-002', 'mtn', 10, 55, 48, 40, 30, 30, Order::STATUS_COMPLETED, $subagent, 2],
            ['DS-DEMO-003', 'telecel', 3, 18, 18, 15, 12, 12, Order::STATUS_COMPLETED, $agent, 2],
            ['DS-DEMO-004', 'at', 2, 12, 12, 10, 8, 8, Order::STATUS_COMPLETED, $agent, 3],
            ['DS-DEMO-005', 'mtn', 20, 100, 90, 78, 60, null, Order::STATUS_PROCESSING, $subagent, 0],
            ['DS-DEMO-006', 'mtn', 1, 8, 7, 6, 5, null, Order::STATUS_FAILED, $agent, 0],
        ];

        foreach ($rows as [$ref, $network, $gb, $customer, $seller, $agentCost, $base, $upstream, $status, $owner, $daysAgo]) {
            /** @var Order $order */
            $order = Order::firstOrCreate(
                ['reference' => $ref],
                [
                    'seller_type' => $owner->getMorphClass(),
                    'seller_id' => $owner->id,
                    'network' => $network,
                    'capacity_gb' => $gb,
                    'beneficiary_phone' => '0209123456',
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
                ]
            );

            $earningStatus = match ($status) {
                Order::STATUS_COMPLETED => Earning::STATUS_CREDITED,
                Order::STATUS_FAILED => Earning::STATUS_REVERSED,
                default => Earning::STATUS_PENDING,
            };

            foreach ($profitSplit->for($order) as $share) {
                Earning::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'earner_type' => $share['earner']->getMorphClass(),
                        'earner_id' => $share['earner']->id,
                        'type' => $share['type'],
                    ],
                    [
                        'amount' => $share['amount'],
                        'status' => $earningStatus,
                        'credited_at' => $earningStatus === Earning::STATUS_CREDITED ? now()->subDays($daysAgo) : null,
                    ]
                );
            }
        }

        // A couple of withdrawal requests against the credited earnings.
        if ($agent->withdrawals()->count() === 0) {
            $agent->withdrawals()->create(['amount' => 5, 'status' => 'pending', 'reference' => 'WD-' . strtoupper(Str::random(6))]);
            $subagent->withdrawals()->create(['amount' => 8, 'status' => 'approved', 'reference' => 'WD-' . strtoupper(Str::random(6))]);
        }

        $this->command?->info('Demo data seeded: 1 agent, 1 subagent, ' . count($rows) . ' orders.');
    }
}
