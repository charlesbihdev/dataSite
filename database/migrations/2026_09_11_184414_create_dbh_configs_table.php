<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// How to reach Databundleshub — connection only, no prices, no cache.
// Single active row; superadmin sets it. DBH is our fulfillment pipe (see ARCHITECTURE §2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dbh_configs', function (Blueprint $table) {
            $table->id();
            $table->string('base_url');                 // DBH API base URL
            $table->text('api_key');                    // encrypted at the model layer
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dbh_configs');
    }
};
