<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Security audit trail for logins across all account types (admin/agent/subagent),
// polymorphic so one table serves every guard. Also records failed attempts.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('authenticatable'); // authenticatable_type + _id (null on a failed unknown login)
            $table->string('guard')->nullable();        // which login door: admin | agent | subagent
            $table->string('identifier')->nullable();   // what was typed (phone/email/username)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('success')->default(true);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
