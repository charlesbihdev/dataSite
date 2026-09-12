<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Earnings pool (DBH's shop_earnings, adapted): profit accrual per sale. Withdrawable,
// SEPARATE from the spendable deposit wallet. Balance = SUM(credited) - SUM(paid withdrawals).
// Fix vs DBH: keyed per (order, earner) — a subagent sale credits BOTH the subagent
// (retail profit) and their agent (commission), so one order can have two earnings.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->morphs('earner');                       // earner_type + earner_id (agent | subagent)
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type');                         // shop_profit | commission
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');   // pending | credited | reversed
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'earner_type', 'earner_id'], 'earnings_order_earner_unique');
            $table->index(['earner_type', 'earner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earnings');
    }
};
