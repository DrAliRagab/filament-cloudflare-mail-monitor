<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Data;

use Carbon\CarbonImmutable;

final readonly class EmailAggregateMetricData
{
    public function __construct(
        public ConfiguredZone $zone,
        public CarbonImmutable $date,
        public ?string $status,
        public int $count,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromCloudflare(ConfiguredZone $configuredZone, array $payload): self
    {
        $dimensions = $payload['dimensions'] ?? [];

        if (! is_array($dimensions)) {
            $dimensions = [];
        }

        return new self(
            zone: $configuredZone,
            date: self::date($dimensions['date'] ?? null),
            status: self::nullableString($dimensions['status'] ?? null),
            count: self::integer($payload['count'] ?? 0),
        );
    }

    private static function date(mixed $value): CarbonImmutable
    {
        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value, 'UTC')->startOfDay();
        }

        return CarbonImmutable::now('UTC')->startOfDay();
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
