<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['agents', 'subagents'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('whatsapp_group_link', 500)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['agents', 'subagents'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('whatsapp_group_link')->nullable()->change();
            });
        }
    }
};
