<?php

namespace Database\Seeders;

use App\Models\Admin;
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
                'email' => 'bihcharles2004@gmail.com',
                'phone' => '0548715098',
                'password' => '@Charles2004',
                'is_active' => true
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

        // 3. Demo Data (runs only locally)
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
