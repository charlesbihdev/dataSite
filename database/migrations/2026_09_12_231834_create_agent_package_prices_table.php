<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('network');                          // mtn | telecel | at
            $table->unsignedSmallInteger('capacity_gb');
            $table->decimal('cost_price', 12, 2);               // agent's tier rate at save time
            $table->decimal('selling_price', 12, 2);            // what the agent charges customers
            $table->decimal('subagent_price', 12, 2)->nullable(); // what sub-agents pay (optional)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['agent_id', 'network', 'capacity_gb']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_package_prices');
    }
};
