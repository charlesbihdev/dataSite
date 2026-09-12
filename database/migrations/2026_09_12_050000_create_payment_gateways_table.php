<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Payment-gateway credentials + rules, one row per gateway (paystack | moolre). Secrets are
// stored encrypted (Laravel cast). Routing between them (Paystack < GHS 1,500, Moolre above for
// agent top-ups; Paystack for public checkout) lives in PaymentGatewayResolver, not here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway')->unique(); // paystack | moolre
            $table->boolean('is_active')->default(false);
            $table->boolean('is_live')->default(false); // paystack test/live
            $table->string('public_key')->nullable();
            $table->text('secret_key')->nullable();      // encrypted
            $table->text('webhook_secret')->nullable();  // encrypted
            $table->string('currency', 8)->default('GHS');
            $table->decimal('min_topup', 10, 2)->default(10);
            $table->decimal('max_topup', 10, 2)->default(10000);
            $table->decimal('charge_percent', 5, 2)->default(0); // processing fee the payer covers
            $table->string('moolre_username')->nullable();
            $table->string('moolre_account_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
