<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Single-row registration settings: the fee a new agent pays and whether self-registration is open.
// Consumed later when the agent portal / self-signup lands.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_configs', function (Blueprint $table): void {
            $table->id();
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_configs');
    }
};
