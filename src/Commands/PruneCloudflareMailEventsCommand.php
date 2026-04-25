<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Commands;

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Console\Command;

final class PruneCloudflareMailEventsCommand extends Command
{
    protected $signature = 'cloudflare-mail-monitor:prune
        {--days= : Override configured retention days}';

    protected $description = 'Delete stored Cloudflare mail monitor events older than the configured retention window.';

    public function handle(): int
    {
        $days = $this->option('days') === null
            ? Config::integer('retention.days', 90)
            : (int) $this->option('days');

        if ($days < 1) {
            $this->components->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

        $deleted = CloudflareMailEvent::query()
            ->where('occurred_at', '<', CarbonImmutable::now('UTC')->subDays($days))
            ->delete();

        $this->components->info(sprintf('Pruned %d Cloudflare mail event(s).', is_int($deleted) ? $deleted : 0));

        return self::SUCCESS;
    }
}
