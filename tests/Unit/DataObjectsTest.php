<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\ConfiguredZone;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\DateRange;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailEventData;

it('rejects missing zone ids', function (): void {
    ConfiguredZone::fromArray(['id' => null]);
})->throws(InvalidArgumentException::class, 'Cloudflare zone ID must be configured.');

it('rejects empty zone ids in the constructor', function (): void {
    new ConfiguredZone('');
})->throws(InvalidArgumentException::class, 'Cloudflare zone ID cannot be empty.');

it('normalizes empty zone names to null', function (): void {
    expect(ConfiguredZone::fromArray(['id' => 'zone-1', 'name' => ''])->name)->toBeNull();
});

it('rejects invalid lookback ranges', function (): void {
    DateRange::forLookbackDays(days: 32, maxDays: 31);
})->throws(InvalidArgumentException::class, 'Lookback days cannot exceed 31 days.');

it('rejects date ranges where the start is not before the end', function (): void {
    new DateRange(
        start: CarbonImmutable::parse('2026-04-25T00:00:00Z'),
        end: CarbonImmutable::parse('2026-04-25T00:00:00Z'),
    );
})->throws(InvalidArgumentException::class, 'Date range start must be before the end.');

it('creates a UTC lookback date range', function (): void {
    $dateRange = DateRange::forLookbackDays(
        days: 1,
        maxDays: 31,
        now: CarbonImmutable::parse('2026-04-25T12:00:00+02:00'),
    );

    expect($dateRange->start->toIso8601String())->toBe('2026-04-24T10:00:00+00:00')
        ->and($dateRange->end->toIso8601String())->toBe('2026-04-25T10:00:00+00:00');
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

    $emailEventData = EmailEventData::fromCloudflare($zone, $payload);
    $sameEvent = EmailEventData::fromCloudflare($zone, $payload);

    expect($emailEventData->status)->toBe('deliveryFailed')
        ->and($emailEventData->isNdr)->toBeTrue()
        ->and($emailEventData->eventHash())->toBe($sameEvent->eventHash())
        ->and($emailEventData->toDatabaseAttributes()['zone_name'])->toBe('example.com');
});

it('normalizes unusual Cloudflare event payload values defensively', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-25T00:00:00Z'));

    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'stringable-value';
        }
    };

    $emailEventData = EmailEventData::fromCloudflare(new ConfiguredZone('zone-1'), [
        'datetime' => null,
        'messageId' => 123,
        'sessionId' => $stringable,
        'from' => [],
        'to' => '',
    ]);

    expect($emailEventData->occurredAt->toIso8601String())->toBe('2026-04-25T00:00:00+00:00')
        ->and($emailEventData->messageId)->toBe('123')
        ->and($emailEventData->sessionId)->toBe('stringable-value')
        ->and($emailEventData->from)->toBeNull()
        ->and($emailEventData->to)->toBeNull();

    CarbonImmutable::setTestNow();
});
