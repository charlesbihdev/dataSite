<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subagent_package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subagent_id')->constrained('subagents')->cascadeOnDelete();
            $table->string('network');                          // mtn | telecel | at
            $table->unsignedSmallInteger('capacity_gb');
            $table->decimal('cost_price', 12, 2);               // the agent's subagent_price at save time
            $table->decimal('selling_price', 12, 2);            // what the subagent charges customers
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subagent_id', 'network', 'capacity_gb']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subagent_package_prices');
    }
};
