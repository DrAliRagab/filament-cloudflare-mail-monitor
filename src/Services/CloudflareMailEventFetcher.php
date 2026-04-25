<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Services;

use DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\CloudflareGraphqlClient;
use DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\Graphql\RecentEmailEventsQuery;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\ConfiguredZone;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\DateRange;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailEventData;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Support\Arr;

final readonly class CloudflareMailEventFetcher
{
    public function __construct(
        private CloudflareGraphqlClient $cloudflareGraphqlClient,
    ) {}

    public function fetch(?DateRange $dateRange = null): int
    {
        $dateRange ??= DateRange::forLookbackDays(
            days: Config::integer('fetch.lookback_days', 1),
            maxDays: Config::integer('fetch.max_lookback_days', 31),
        );

        $stored = 0;

        foreach (Config::zones() as $zoneConfig) {
            $zone = ConfiguredZone::fromArray($zoneConfig);

            foreach ($this->eventsForZone($zone, $dateRange) as $event) {
                CloudflareMailEvent::query()->updateOrCreate(
                    ['event_hash' => $event->eventHash()],
                    $event->toDatabaseAttributes(),
                );

                ++$stored;
            }
        }

        return $stored;
    }

    /**
     * @return list<EmailEventData>
     */
    private function eventsForZone(ConfiguredZone $configuredZone, DateRange $dateRange): array
    {
        $emailEvents = [];
        $datetimeBefore = null;
        $pageSize = Config::integer('fetch.page_size', 500);
        $lastEvent = null;

        do {
            $data = $this->cloudflareGraphqlClient->query(RecentEmailEventsQuery::QUERY, [
                'zoneTag' => $configuredZone->id,
                'start' => $dateRange->start->toIso8601String(),
                'end' => $dateRange->end->toIso8601String(),
                'limit' => $pageSize,
                'datetimeBefore' => $datetimeBefore,
            ]);

            $events = Arr::get($data, 'viewer.zones.0.emailSendingAdaptive', []);

            if (! is_array($events) || $events === []) {
                break;
            }

            $pageEvents = [];

            foreach ($events as $event) {
                if (! is_array($event)) {
                    continue;
                }

                $pageEvents[] = EmailEventData::fromCloudflare($configuredZone, $this->stringKeyedArray($event));
            }

            $emailEvents = [...$emailEvents, ...$pageEvents];

            $lastEvent = $pageEvents === [] ? null : $pageEvents[array_key_last($pageEvents)];
            $datetimeBefore = $lastEvent?->occurredAt->toIso8601String();
        } while ($lastEvent instanceof EmailEventData && count($events) === $pageSize);

        return $emailEvents;
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
