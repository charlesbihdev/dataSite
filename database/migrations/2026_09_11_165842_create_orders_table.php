<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One orders table (status-driven) instead of Databundleshub's four. Covers the
// full life cycle: seller's wallet debited -> dispatched upstream -> polled -> settled.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // our ref + idempotency key to upstream

            // Who sold it (agent or subagent) — whose wallet was debited.
            $table->morphs('seller');                       // seller_type + seller_id

            $table->string('network');                      // mtn | telecel | at
            $table->decimal('capacity_gb', 8, 2);
            $table->string('beneficiary_phone');            // the guest customer's number

            $table->string('channel')->index();             // prepaid (deposit) | online (customer paid)

            // Cascade snapshot, frozen at sale time so profit-sharing is pure subtraction
            // and immune to later price edits. Each party's cut = difference of adjacent levels.
            $table->decimal('customer_price', 12, 2);        // top: what the customer pays
            $table->decimal('seller_cost', 12, 2);          // what the seller pays (wallet-debit basis)
            $table->decimal('agent_cost', 12, 2);           // agent-in-chain cost (= seller_cost if agent sold)
            $table->decimal('base_cost', 12, 2)->nullable(); // our platform cost at sale (expected base)

            $table->string('status')->default('pending')->index(); // pending|processing|completed|failed

            // Databundleshub upstream tracking (poll-based; no callback from them).
            $table->string('upstream_request_id')->nullable()->index();
            $table->string('upstream_reference')->nullable()->index();
            $table->string('upstream_status')->nullable();
            $table->decimal('upstream_cost', 12, 2)->nullable(); // our base cost from DBH
            $table->string('failure_reason')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(['seller_type', 'seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
