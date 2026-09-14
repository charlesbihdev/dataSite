<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A pending record of real money coming IN through a payment gateway (Paystack/Moolre).
// It is created BEFORE the gateway redirect and only credits the wallet once the gateway
// confirms the charge (callback or webhook). `amount` = what we credit the wallet (the base
// the agent asked for); `charged_amount` = the gross we actually billed (base + gateway fee).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('gateway');                          // paystack | moolre
            $table->string('reference')->unique();              // our reference (MTP_...)
            $table->string('gateway_reference')->nullable()->index(); // gateway's own reference
            $table->decimal('amount', 14, 2);                   // credited to wallet on success
            $table->decimal('charged_amount', 14, 2);           // gross billed at the gateway
            $table->string('currency', 3)->default('GHS');
            $table->string('status')->default('pending');       // pending | success | failed
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_topups');
    }
};
