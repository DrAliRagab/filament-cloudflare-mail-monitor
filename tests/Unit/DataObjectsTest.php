<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\ConfiguredZone;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\DateRange;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailEventData;

it('rejects missing zone ids', function (): void {
    ConfiguredZone::fromArray(['id' => null]);
})->throws(InvalidArgumentException::class, 'Cloudflare zone ID must be configured.');

it('rejects invalid lookback ranges', function (): void {
    DateRange::forLookbackDays(days: 32, maxDays: 31);
})->throws(InvalidArgumentException::class, 'Lookback days cannot exceed 31 days.');

it('creates a UTC lookback date range', function (): void {
    $range = DateRange::forLookbackDays(
        days: 1,
        maxDays: 31,
        now: CarbonImmutable::parse('2026-04-25T12:00:00+02:00'),
    );

    expect($range->start->toIso8601String())->toBe('2026-04-24T10:00:00+00:00')
        ->and($range->end->toIso8601String())->toBe('2026-04-25T10:00:00+00:00');
});

it('maps Cloudflare event fields and builds stable event hashes', function (): void {
    $zone = new ConfiguredZone('zone-1', 'example.com');
    $payload = [
        'datetime' => '2026-04-25T10:15:00Z',
        'messageId' => 'message-1',
        'to' => 'user@example.net',
        'status' => 'deliveryFailed',
        'eventType' => 'delivery',
        'errorCause' => 'recipient_rejected',
        'isNDR' => 1,
    ];

    $event = EmailEventData::fromCloudflare($zone, $payload);
    $sameEvent = EmailEventData::fromCloudflare($zone, $payload);

    expect($event->status)->toBe('deliveryFailed')
        ->and($event->isNdr)->toBeTrue()
        ->and($event->eventHash())->toBe($sameEvent->eventHash())
        ->and($event->toDatabaseAttributes()['zone_name'])->toBe('example.com');
});
