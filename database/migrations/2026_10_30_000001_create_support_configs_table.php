<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin support contact shown to agents (singleton row, latest wins).
        Schema::create('support_configs', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_number', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_configs');
    }
};
