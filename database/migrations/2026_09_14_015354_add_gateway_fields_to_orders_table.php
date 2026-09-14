<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Online (storefront) orders are paid by the customer through a gateway. We record which gateway
// took the payment and its reference so the payment can be verified (callback, webhook, or admin),
// plus the buyer's email the gateway needs for the checkout + receipt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->after('beneficiary_phone');
            $table->string('gateway')->nullable()->after('payment_status');       // paystack | moolre
            $table->string('gateway_reference')->nullable()->index()->after('gateway');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_email', 'gateway', 'gateway_reference']);
        });
    }
};
