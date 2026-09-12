<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Withdrawal requests against the earnings pool (DBH's shop_withdrawals, adapted to
// the earner instead of a shop). Request -> admin approves -> paid out.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->morphs('earner');                       // earner_type + earner_id (agent | subagent)
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');   // pending | approved | paid | rejected
            $table->string('reference')->nullable()->unique();
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['earner_type', 'earner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
