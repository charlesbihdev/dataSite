<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inbound developer-API requests (resellers hitting our API). Append-only, no updated_at.
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('seller');                   // nullable: a bad-key 401 is logged too
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('method', 8);
            $table->string('endpoint');
            $table->string('network')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('success')->default(false);         // 2xx AND business success
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['network', 'success']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
