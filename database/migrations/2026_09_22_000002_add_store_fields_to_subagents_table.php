<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subagents', function (Blueprint $table) {
            $table->string('store_name')->nullable()->after('slug');
            $table->string('whatsapp_number', 20)->nullable()->after('store_name');
            $table->string('whatsapp_group_link')->nullable()->after('whatsapp_number');
            $table->unsignedInteger('referral_clicks')->default(0)->after('whatsapp_group_link');
            $table->longText('referral_qr')->nullable()->after('referral_clicks');   // cached PNG data URI
            $table->boolean('store_active')->default(true)->after('referral_qr');     // storefront on/off
        });
    }

    public function down(): void
    {
        Schema::table('subagents', function (Blueprint $table) {
            $table->dropColumn(['store_name', 'whatsapp_number', 'whatsapp_group_link', 'referral_clicks', 'referral_qr', 'store_active']);
        });
    }
};
