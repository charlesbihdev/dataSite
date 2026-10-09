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
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->text('public_key')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->text('failure_reason')->nullable()->change();
        });

        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });

        if (Schema::hasTable('upstream_api_logs')) {
            Schema::table('upstream_api_logs', function (Blueprint $table): void {
                $table->text('request_url')->change();
                $table->text('error_message')->nullable()->change();
            });
        }

        if (Schema::hasTable('api_request_logs')) {
            Schema::table('api_request_logs', function (Blueprint $table): void {
                $table->text('error_message')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->string('public_key')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('failure_reason')->nullable()->change();
        });

        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->string('description')->nullable()->change();
        });

        if (Schema::hasTable('upstream_api_logs')) {
            Schema::table('upstream_api_logs', function (Blueprint $table): void {
                $table->string('request_url')->change();
                $table->string('error_message')->nullable()->change();
            });
        }

        if (Schema::hasTable('api_request_logs')) {
            Schema::table('api_request_logs', function (Blueprint $table): void {
                $table->string('error_message')->nullable()->change();
            });
        }
    }
};
