<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Retailers. Belong to exactly one agent; sell to end customers. Cannot recruit.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subagents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();          // primary login id
            $table->string('email')->nullable()->unique();
            $table->string('username')->nullable()->unique();
            $table->string('slug')->nullable()->unique(); // storefront handle -> /{slug}
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // The owning agent. Required — a subagent always has a parent agent.
            // Deleting an agent is blocked while subagents still point to them.
            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subagents');
    }
};
