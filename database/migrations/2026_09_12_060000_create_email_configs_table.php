<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A single-row SMTP config the mailer reads at runtime instead of .env, so admins can change the
// sending account without a deploy. smtp_password is stored encrypted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('from_email')->default('noreply@datasite.test');
            $table->string('from_name')->default('DataSite');
            $table->boolean('smtp_enabled')->default(false);
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->default(587);
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable(); // encrypted
            $table->string('smtp_encryption', 8)->default('tls'); // tls | ssl | none
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_configs');
    }
};
