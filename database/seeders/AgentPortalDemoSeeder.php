<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Earning;
use App\Models\Order;
use App\Models\Subagent;
use App\Models\Withdrawal;
use App\Services\Orders\ProfitSplit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dev-only: fills ONE agent's portal so the Orders and Transactions tables show real data.
 * Funds the wallet, then seeds a spread of orders each with a matching wallet-ledger row (purchase
 * debits, an admin adjustment, a reversal) so both /orders and /transactions are populated.
 *
 * Target: the agent named by `SEED_AGENT` (username / phone / email), else the newest agent.
 * Run: php artisan db:seed --class=AgentPortalDemoSeeder
 */
class AgentPortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('AgentPortalDemoSeeder skipped in production.');

            return;
        }

        $agent = $this->resolveAgent();
        if ($agent === null) {
            $this->command?->error('No agent found to seed. Create an agent first, or set SEED_AGENT.');

            return;
        }

        // A recruited sub-agent so the My Sub-Agents roster isn't blank. Independent of the order
        // seeding below so it still fills in for an agent that already has seeded orders.
        if ($agent->subagents()->count() === 0) {
            $subagent = $agent->subagents()->create([
                'name' => 'Karl Seven',
                'username' => 'karl'.$agent->id.'seed',
                'phone' => '05'.str_pad((string) (94000000 + $agent->id), 8, '0', STR_PAD_LEFT),
                'slug' => 'karl-seven-'.$agent->id,
                'password' => '@Charles2004',
                'is_active' => true,
            ]);
            $subagent->walletOrCreate();
        }

        if (! $agent->orders()->where('reference', 'like', 'DS-SEED-%')->exists()) {
            $this->seedOrders($agent);
        }

        $this->seedSubagentSales($agent);
        $this->seedEarningsAndWithdrawal($agent);

        $this->command?->info("Seeded agent [{$agent->username}]. Wallet: ".number_format((float) $agent->walletOrCreate()->fresh()->balance, 2).' · Available earnings: '.number_format($agent->earningsBalance(), 2));
    }

    private function seedOrders(Agent $agent): void
    {
        $wallet = $agent->walletOrCreate();
        $wallet->credit(1000, 'topup', 'PSTK-'.strtoupper(fake()->bothify('??####')), 'Wallet top-up');

        // ref suffix, network, phone, gb, customer, seller, agentCost, base, status, daysAgo
        $rows = [
            ['001', 'mtn', '0241234567', 5, 30, 25, 20, 15, Order::STATUS_COMPLETED, 1],
            ['002', 'mtn', '0559876543', 10, 55, 48, 40, 30, Order::STATUS_COMPLETED, 2],
            ['003', 'telecel', '0201112223', 10, 52, 45, 38, 30, Order::STATUS_COMPLETED, 4],
            ['004', 'at', '0261234567', 3, 15, 13, 11, 9, Order::STATUS_COMPLETED, 6],
            ['005', 'mtn', '0244556677', 20, 100, 90, 78, 60, Order::STATUS_PROCESSING, 0],
            ['006', 'mtn', '0554443332', 1, 8, 7, 6, 5, Order::STATUS_FAILED, 0],
        ];

        foreach ($rows as [$suffix, $network, $phone, $gb, $customer, $seller, $agentCost, $base, $status, $daysAgo]) {
            $reference = 'DS-SEED-'.$suffix;
            $when = now()->subDays($daysAgo);

            /** @var Order $order */
            $order = $agent->orders()->create([
                'reference' => $reference,
                'idempotency_key' => $reference,
                'source' => Order::SOURCE_PORTAL,
                'payment_status' => Order::PAYMENT_PAID,
                'network' => $network,
                'capacity_gb' => $gb,
                'beneficiary_phone' => $phone,
                'channel' => Order::CHANNEL_PREPAID,
                'customer_price' => $customer,
                'seller_cost' => $seller,
                'agent_cost' => $agentCost,
                'base_cost' => $base,
                'status' => $status,
                'completed_at' => $status === Order::STATUS_COMPLETED ? $when : null,
                'failed_at' => $status === Order::STATUS_FAILED ? $when : null,
                'created_at' => $when,
                'updated_at' => $when,
            ]);

            // Ledger: every order debits the wallet; a failed one is reversed (credited back).
            $wallet->debit($seller, 'order_purchase', $reference, "Bundle purchase {$reference}");
            if ($status === Order::STATUS_FAILED) {
                $wallet->credit($seller, 'reversal', $reference, "Reversal {$reference}");
            }
        }

        // One admin deduction, so the Source=ADMIN / Wallet-debit filter has something to show.
        $wallet->debit(20, 'adjustment', 'ADJ-SEED', 'Admin balance correction');
    }

    /**
     * Real earnings derived from delivered orders via ProfitSplit (the agent's own shop profit plus
     * commission from sub-agent sales), so Total Earnings reflects actual margin — not an invented
     * figure. Plus one pending withdrawal for the history. Runs after orders + sub-agent sales exist.
     */
    private function seedEarningsAndWithdrawal(Agent $agent): void
    {
        if ($agent->earnings()->count() > 0) {
            return;
        }

        $subIds = $agent->subagents()->pluck('id');
        $orders = Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->where(function ($q) use ($agent, $subIds) {
                $q->where(fn ($a) => $a->where('seller_type', $agent->getMorphClass())->where('seller_id', $agent->id))
                    ->orWhere(fn ($s) => $s->where('seller_type', (new Subagent)->getMorphClass())->whereIn('seller_id', $subIds));
            })
            ->get();

        $split = new ProfitSplit;
        foreach ($orders as $order) {
            foreach ($split->for($order) as $share) {
                $share['earner']->earnings()->firstOrCreate(
                    ['order_id' => $order->id, 'type' => $share['type']],
                    ['amount' => $share['amount'], 'status' => Earning::STATUS_CREDITED, 'credited_at' => now()],
                );
            }
        }

        if ($agent->earningsBalance() >= 10) {
            $agent->withdrawals()->create([
                'amount' => 10.0,
                'method' => 'momo',
                'destination' => $agent->phone,
                'status' => Withdrawal::STATUS_PENDING,
                'reference' => 'WD-'.strtoupper(Str::random(8)),
            ]);
        }
    }

    /**
     * Orders sold through the agent's first sub-agent's storefront, so the Sub-agent Sales page has
     * rows and a 30-day margin. Agent margin per order = seller_cost − agent_cost.
     */
    private function seedSubagentSales(Agent $agent): void
    {
        $subagent = $agent->subagents()->first();
        if ($subagent === null || $subagent->orders()->where('reference', 'like', 'SA-SEED-%')->exists()) {
            return;
        }

        // ref, network, phone, gb, customer, seller(subagent pays), agentCost, base, status, daysAgo
        $rows = [
            ['001', 'mtn', '0248880001', 5, 35, 30, 25, 15, Order::STATUS_COMPLETED, 2],
            ['002', 'mtn', '0248880002', 10, 62, 55, 45, 30, Order::STATUS_COMPLETED, 5],
            ['003', 'telecel', '0208880003', 10, 60, 52, 44, 30, Order::STATUS_PROCESSING, 0],
        ];

        foreach ($rows as [$suffix, $network, $phone, $gb, $customer, $seller, $agentCost, $base, $status, $daysAgo]) {
            $when = now()->subDays($daysAgo);
            $subagent->orders()->create([
                'reference' => 'SA-SEED-'.$suffix,
                'idempotency_key' => 'SA-SEED-'.$suffix,
                'source' => Order::SOURCE_STOREFRONT,
                'payment_status' => Order::PAYMENT_PAID,
                'network' => $network,
                'capacity_gb' => $gb,
                'beneficiary_phone' => $phone,
                'channel' => Order::CHANNEL_PREPAID,
                'customer_price' => $customer,
                'seller_cost' => $seller,
                'agent_cost' => $agentCost,
                'base_cost' => $base,
                'status' => $status,
                'completed_at' => $status === Order::STATUS_COMPLETED ? $when : null,
                'created_at' => $when,
                'updated_at' => $when,
            ]);
        }
    }

    private function resolveAgent(): ?Agent
    {
        $key = (string) env('SEED_AGENT', '');
        if ($key !== '') {
            return Agent::query()
                ->where('username', $key)
                ->orWhere('phone', $key)
                ->orWhere('email', $key)
                ->first();
        }

        return Agent::query()->latest('id')->first();
    }
}
