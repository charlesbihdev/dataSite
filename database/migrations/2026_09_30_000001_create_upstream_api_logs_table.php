<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-call audit of every request to Databundleshub (create_order + status polls).
        // An append-only observability log — never edited, so a single created_at, no updated_at.
        Schema::create('upstream_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('operation');                        // create | status
            $table->string('network')->nullable();              // mtn | telecel | at
            $table->string('request_url');
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable(); // null when the supplier was never reached
            $table->text('response_body')->nullable();
            $table->string('upstream_request_id')->nullable();
            $table->boolean('success')->default(false);
            $table->string('outcome')->nullable();              // delivered | failed | accepted | processing | error
            $table->string('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['network', 'success']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upstream_api_logs');
    }
};
