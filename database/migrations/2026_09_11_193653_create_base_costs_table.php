<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What we pay Databundleshub per network/GB — the cost source, admin-set by hand (DBH has no
// base-cost concept; it's the top of its own chain). Tier-independent: one cost band applies to
// every tier and acts as the floor under our selling rates. Same band shape as tier_prices.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_costs', function (Blueprint $table) {
            $table->id();
            $table->string('network');                  // mtn | telecel | at
            $table->decimal('min_gb', 8, 2);
            $table->decimal('max_gb', 8, 2);
            $table->decimal('cost_per_gb', 10, 2);      // what Databundleshub charges us
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['network', 'min_gb', 'max_gb'], 'base_costs_unique_band');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_costs');
    }
};
