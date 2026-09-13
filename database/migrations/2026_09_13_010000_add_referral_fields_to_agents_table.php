<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('store_name')->nullable()->after('slug');            // shown on referral checkout
            $table->string('whatsapp_number')->nullable()->after('store_name');
            $table->string('whatsapp_group_link')->nullable()->after('whatsapp_number');
            $table->unsignedInteger('referral_clicks')->default(0)->after('whatsapp_group_link');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['store_name', 'whatsapp_number', 'whatsapp_group_link', 'referral_clicks']);
        });
    }
};
