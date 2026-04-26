<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class EmailSuppressionData
{
    /**
     * @param  list<string>  $zones
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ConfiguredZone $zone,
        public string $id,
        public string $email,
        public string $reason,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $expiresAt,
        public array $zones,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromCloudflare(ConfiguredZone $configuredZone, array $payload): self
    {
        return new self(
            zone: $configuredZone,
            id: self::string($payload['id'] ?? null),
            email: self::string($payload['email'] ?? null),
            reason: self::string($payload['reason'] ?? null),
            createdAt: self::dateTime($payload['created_at'] ?? null),
            expiresAt: self::nullableDateTime($payload['expires_at'] ?? null),
            zones: self::stringList($payload['zones'] ?? []),
            raw: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'reason' => $this->reason,
            'created_at' => $this->createdAt->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'zone_id' => $this->zone->id,
            'zone_name' => $this->zone->name ?? $this->zone->id,
            'zones' => $this->zones,
        ];
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface || is_string($value) || is_int($value) || is_float($value)) {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        }

        return CarbonImmutable::now('UTC');
    }

    private static function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::dateTime($value);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        }

        return $strings;
    }
}
