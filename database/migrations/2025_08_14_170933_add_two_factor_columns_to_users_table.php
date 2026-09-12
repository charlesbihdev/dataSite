<?php

use Illuminate\Database\Migrations\Migration;

// The 'users' table does not exist in DataSite. Agents, admins, and subagents
// have their own tables with two-factor columns already baked into their
// respective create-table migrations. This file is a Fortify starter-kit
// leftover and has been intentionally emptied.

return new class extends Migration
{
    public function up(): void
    {
        // no-op: 2FA columns live on agents/admins/subagents tables, not users.
    }

    public function down(): void
    {
        // no-op
    }
};
