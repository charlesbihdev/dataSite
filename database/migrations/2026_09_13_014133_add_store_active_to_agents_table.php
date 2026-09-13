<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // The storefront on/off switch, separate from `is_active` (account login). An agent can
            // take their public /buy/{slug} store offline without losing portal access.
            $table->boolean('store_active')->default(true)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('store_active');
        });
    }
};
