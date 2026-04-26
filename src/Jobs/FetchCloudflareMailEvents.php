<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Jobs;

use DrAliRagab\FilamentCloudflareMailMonitor\Data\DateRange;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailEventFetcher;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSuppressionFetcher;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class FetchCloudflareMailEvents implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly ?int $lookbackDays = null,
    ) {}

    public function handle(CloudflareMailEventFetcher $cloudflareMailEventFetcher, CloudflareMailSuppressionFetcher $cloudflareMailSuppressionFetcher): void
    {
        $range = $this->lookbackDays === null
            ? null
            : DateRange::forLookbackDays(
                days: $this->lookbackDays,
                maxDays: Config::integer('fetch.max_lookback_days', 31),
            );

        $cloudflareMailEventFetcher->fetch($range);
        $cloudflareMailSuppressionFetcher->fetch();
    }
}
