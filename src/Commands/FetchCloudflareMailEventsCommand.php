<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Commands;

use DrAliRagab\FilamentCloudflareMailMonitor\Data\DateRange;
use DrAliRagab\FilamentCloudflareMailMonitor\Jobs\FetchCloudflareMailEvents;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailEventFetcher;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class FetchCloudflareMailEventsCommand extends Command
{
    protected $signature = 'cloudflare-mail-monitor:fetch
        {--days= : Override configured lookback days}
        {--queue : Dispatch the fetch job instead of running synchronously}';

    protected $description = 'Fetch outbound Cloudflare Email Service events into the local monitor table.';

    public function handle(CloudflareMailEventFetcher $cloudflareMailEventFetcher): int
    {
        $days = $this->option('days') === null
            ? Config::integer('fetch.lookback_days', 1)
            : (int) $this->option('days');

        try {
            $range = DateRange::forLookbackDays(
                days: $days,
                maxDays: Config::integer('fetch.max_lookback_days', 31),
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->components->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        if ($this->option('queue')) {
            FetchCloudflareMailEvents::dispatch($days);
            $this->components->info('Cloudflare mail event fetch job dispatched.');

            return self::SUCCESS;
        }

        $stored = $cloudflareMailEventFetcher->fetch($range);

        $this->components->info(sprintf('Stored %d Cloudflare mail event(s).', $stored));

        return self::SUCCESS;
    }
}
