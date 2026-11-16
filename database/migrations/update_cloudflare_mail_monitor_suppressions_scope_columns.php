<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloudflare_mail_monitor_suppressions', function (Blueprint $table): void {
            $table->string('scope_type')->nullable();
            $table->string('scope_value', 1024)->nullable();
            $table->boolean('read_only')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cloudflare_mail_monitor_suppressions', function (Blueprint $table): void {
            $table->dropColumn(['scope_type', 'scope_value', 'read_only']);
        });
    }
};
