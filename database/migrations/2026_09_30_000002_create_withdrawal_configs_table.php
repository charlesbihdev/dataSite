<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-configurable withdrawal amount thresholds (singleton row, latest wins).
        Schema::create('withdrawal_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_amount', 12, 2)->default(20);
            $table->decimal('max_amount', 12, 2)->nullable(); // null = no maximum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_configs');
    }
};
