<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\BaseCost;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Initial Admin Account
        Admin::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@datasite.com',
                'phone' => '0548715098',
                'password' => '@TestAdmin2026',
                'is_active' => true,
            ]
        );

        // 2. Create the Default "Standard" Tier
        $tier = PricingTier::firstOrCreate(
            ['name' => 'Standard'],
            [
                'is_active' => true,
                'is_default' => true,
                'is_undeletable' => true,
            ]
        );

        // Ensure it's locked down if it already existed before flags were added
        if (! $tier->is_default || ! $tier->is_undeletable) {
            $tier->update(['is_default' => true, 'is_undeletable' => true]);
        }

        // 2. Base Costs & Tier Prices (from DBH "agent" role defaults)
        $initialBands = [
            // Network, Min, Max, BaseCost, TierPrice (Standard)
            ['mtn', 1, 100, 4.00, 4.50],
            ['telecel', 1, 100, 4.00, 4.50],
            ['at', 1, 100, 4.00, 4.50],
            ['default', 1, 100, 4.00, 4.50],
        ];

        foreach ($initialBands as [$net, $min, $max, $cost, $sell]) {
            BaseCost::firstOrCreate(
                ['network' => $net, 'min_gb' => $min, 'max_gb' => $max],
                ['cost_per_gb' => $cost, 'is_active' => true],
            );
            TierPrice::firstOrCreate(
                ['pricing_tier_id' => $tier->id, 'network' => $net, 'min_gb' => $min, 'max_gb' => $max],
                ['price_per_gb' => $sell, 'is_active' => true],
            );
        }

        // 3. Starter Agent account (production-safe, idempotent). This is a REAL agent — the demo
        // seeders are skipped in production, so without this there would be no agent to log in with.
        $agent = Agent::firstOrCreate(
            ['username' => 'agent'],
            [
                'name' => 'First Agent',
                'email' => 'agent@datasite.com',
                'phone' => '0500000000',
                'slug' => 'agent',
                'password' => '@Agent2026',
                'pricing_tier_id' => $tier->id,
                'is_active' => true,
                'store_active' => true,
            ]
        );

        // Make sure the agent is on the default tier and has a wallet, even if the row pre-existed.
        if ($agent->pricing_tier_id === null) {
            $agent->update(['pricing_tier_id' => $tier->id]);
        }
        $agent->walletOrCreate();

        // 4. Demo Data (runs only locally)
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
