<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// API intake: a client-supplied idempotency key (so a retry returns the same order instead of
// double-charging) and where the order came from. Idempotency is scoped per seller.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source')->default('portal')->after('channel'); // portal | api
            $table->string('idempotency_key')->nullable()->after('reference');
            $table->unique(['seller_type', 'seller_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['seller_type', 'seller_id', 'idempotency_key']);
            $table->dropColumn(['source', 'idempotency_key']);
        });
    }
};
