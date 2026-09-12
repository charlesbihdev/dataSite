<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One wallet per owner (agent or subagent), polymorphic. `balance` is a cached
// running total; the true source of truth is the wallet_transactions ledger.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->morphs('walletable');                    // walletable_type + walletable_id
            $table->decimal('balance', 14, 2)->default(0);   // cached total, rebuildable from ledger
            $table->string('currency', 3)->default('GHS');
            $table->timestamps();

            $table->unique(['walletable_type', 'walletable_id']); // one wallet per owner
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
