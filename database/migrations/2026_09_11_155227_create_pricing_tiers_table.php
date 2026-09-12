<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tiers concern, self-contained: the tier list + the link from agents to a tier.
// A tier decides which price rows an agent buys at (rows added in the pricing slice).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Agent", "Dealer", "Gold"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Attach the tier to agents by id (stable) rather than by name.
        // Nullable until assigned; if a tier is deleted the agent stays, link nulls.
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('pricing_tier_id')
                ->nullable()
                ->after('password')
                ->constrained('pricing_tiers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_tier_id');
        });

        Schema::dropIfExists('pricing_tiers');
    }
};
