<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer API credentials for a seller (agent/subagent). The raw key is shown once at
// creation and only its SHA-256 hash is stored — same pattern as Databundleshub's api_keys.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');                 // owner_type + owner_id (Agent/Subagent)
            $table->string('name');                  // human label, e.g. "Production"
            $table->string('prefix', 16)->index();   // shown for identification, e.g. "dsk_ab12cd34"
            $table->string('key_hash', 64)->unique(); // sha-256 of the raw key
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
