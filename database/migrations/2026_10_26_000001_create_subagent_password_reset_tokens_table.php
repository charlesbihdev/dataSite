<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Own table: subagent emails are only unique among subagents, so sharing the agents' table (keyed
// on email) would let one tier's reset request clobber or satisfy the other's token.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subagent_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subagent_password_reset_tokens');
    }
};
