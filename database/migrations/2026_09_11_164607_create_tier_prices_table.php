<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The pricing rows — one table (Databundleshub style), but tier referenced by id.
// One row = "for this tier, on this network, for GB in [min,max], charge this per GB".
// Seeder creates the defaults; admin edits/adds rows from the UI afterward.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_tier_id')->constrained('pricing_tiers')->cascadeOnDelete();
            $table->string('network');                  // mtn | telecel | at
            $table->decimal('min_gb', 8, 2);            // range start (GB)
            $table->decimal('max_gb', 8, 2);            // range end (GB)
            $table->decimal('price_per_gb', 10, 2);     // OUR selling rate
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent exact duplicate rows for the same tier/network/range.
            $table->unique(['pricing_tier_id', 'network', 'min_gb', 'max_gb'], 'tier_prices_unique_band');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_prices');
    }
};
