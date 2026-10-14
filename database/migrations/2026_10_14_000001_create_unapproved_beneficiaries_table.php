<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unapproved_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('network', 20)->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('source_label', 100)->nullable();
            $table->string('status', 20)->default('pending'); // 'pending', 'submitted', 'approved'
            $table->text('failure_message')->nullable();
            $table->unsignedInteger('check_count')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('network');
            $table->index('last_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unapproved_beneficiaries');
    }
};

