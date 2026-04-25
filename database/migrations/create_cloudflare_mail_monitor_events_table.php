<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_mail_monitor_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_hash', 64)->unique();
            $table->string('zone_id')->index();
            $table->string('zone_name')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('message_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('from')->nullable()->index();
            $table->string('to')->nullable()->index();
            $table->text('subject')->nullable();
            $table->string('status')->nullable()->index();
            $table->string('event_type')->nullable()->index();
            $table->string('sending_domain')->nullable()->index();
            $table->string('error_cause')->nullable()->index();
            $table->text('error_detail')->nullable();
            $table->string('arc')->nullable();
            $table->string('dkim')->nullable()->index();
            $table->string('dmarc')->nullable()->index();
            $table->string('spf')->nullable()->index();
            $table->boolean('is_spam')->default(false)->index();
            $table->boolean('is_ndr')->default(false)->index();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['zone_id', 'occurred_at']);
            $table->index(['zone_id', 'status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_mail_monitor_events');
    }
};
