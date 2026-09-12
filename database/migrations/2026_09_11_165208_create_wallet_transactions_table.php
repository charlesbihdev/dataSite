<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The ledger — the source of truth for every balance movement. `amount` is signed
// (credit positive, debit negative) so SUM(amount) reconciles to wallets.balance.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type');                       // topup | purchase | commission | reversal | transfer
            $table->decimal('amount', 14, 2);             // signed: + credit, - debit
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference')->nullable()->index(); // links to order/payment/etc.
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
