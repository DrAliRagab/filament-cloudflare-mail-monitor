<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Services;

use DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\CloudflareGraphqlClient;
use DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\Graphql\EmailSendingStatusCountsQuery;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\ConfiguredZone;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\DateRange;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailAggregateMetricData;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Support\Arr;

final readonly class CloudflareMailAggregateFetcher
{
    public function __construct(
        private CloudflareGraphqlClient $cloudflareGraphqlClient,
    ) {}

    /**
     * @return list<EmailAggregateMetricData>
     */
    public function statusCounts(?DateRange $dateRange = null): array
    {
        $dateRange ??= DateRange::forLookbackDays(
            days: Config::integer('fetch.lookback_days', 1),
            maxDays: Config::integer('fetch.max_lookback_days', 31),
        );

        $metrics = [];

        foreach (Config::zones() as $zoneConfig) {
            $zone = ConfiguredZone::fromArray($zoneConfig);

            foreach ($this->statusCountsForZone($zone, $dateRange) as $metric) {
                $metrics[] = $metric;
            }
        }

        return $metrics;
    }

    /**
     * @return list<EmailAggregateMetricData>
     */
    private function statusCountsForZone(ConfiguredZone $configuredZone, DateRange $dateRange): array
    {
        $data = $this->cloudflareGraphqlClient->query(EmailSendingStatusCountsQuery::QUERY, [
            'zoneTag' => $configuredZone->id,
            'start' => $dateRange->start->toDateString(),
            'end' => $dateRange->end->toDateString(),
            'limit' => Config::integer('fetch.page_size', 500),
        ]);

        $groups = Arr::get($data, 'viewer.zones.0.emailSendingAdaptiveGroups', []);

        if (! is_array($groups)) {
            return [];
        }

        $metrics = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $metrics[] = EmailAggregateMetricData::fromCloudflare($configuredZone, $this->stringKeyedArray($group));
        }

        return $metrics;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $stringKeyed = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $stringKeyed[$key] = $value;
            }
        }

        return $stringKeyed;
    }
}
