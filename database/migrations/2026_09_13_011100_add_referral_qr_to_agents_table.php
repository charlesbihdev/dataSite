<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // The generated QR (PNG data URI) for the referral link, cached so it isn't rebuilt per view.
            $table->longText('referral_qr')->nullable()->after('referral_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('referral_qr');
        });
    }
};
