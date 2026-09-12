<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Payment lifecycle, orthogonal to the fulfillment `status`. Wallet-funded orders (agent/subagent
// portal + API) are paid the instant they exist, so they default to "paid" and dispatch straight
// away. Public storefront orders start "awaiting" and only dispatch once the gateway confirms.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('paid')->after('source'); // paid | awaiting | failed
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
