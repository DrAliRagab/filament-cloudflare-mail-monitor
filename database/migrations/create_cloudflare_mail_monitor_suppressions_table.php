<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_mail_monitor_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('suppression_id');
            $table->string('zone_id')->index();
            $table->string('zone_name')->nullable();
            $table->string('email')->index();
            $table->string('reason')->index();
            $table->timestamp('suppressed_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('cloudflare_zones')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['zone_id', 'suppression_id'], 'cf_mail_suppressions_zone_suppression_unique');
            $table->index(['zone_id', 'reason', 'suppressed_at'], 'cf_mail_suppressions_zone_reason_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_mail_monitor_suppressions');
    }
};
