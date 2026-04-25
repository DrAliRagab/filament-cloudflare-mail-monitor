<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\ConfiguredZone;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailEventData;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;

beforeEach(function (): void {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
});

it('persists normalized Cloudflare email event data', function (): void {
    $event = EmailEventData::fromCloudflare(
        zone: new ConfiguredZone('zone-1', 'example.com'),
        payload: [
            'datetime' => '2026-04-25T10:15:00Z',
            'messageId' => 'message-1',
            'sessionId' => 'session-1',
            'from' => 'sender@example.com',
            'to' => 'user@example.net',
            'subject' => 'Welcome',
            'status' => 'delivered',
            'eventType' => 'delivery',
            'sendingDomain' => 'example.com',
            'dkim' => 'pass',
            'dmarc' => 'pass',
            'spf' => 'pass',
            'isSpam' => 0,
            'isNDR' => 0,
        ],
    );

    $record = CloudflareMailEvent::query()->create($event->toDatabaseAttributes());

    expect($record->event_hash)->toHaveLength(64)
        ->and($record->occurred_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($record->is_spam)->toBeFalse()
        ->and($record->raw)->toHaveKey('messageId', 'message-1');
});

it('can scope events by zone and date range', function (): void {
    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('a', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
    ]);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('b', 64),
        'zone_id' => 'zone-2',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
    ]);

    $count = CloudflareMailEvent::query()
        ->forZone('zone-1')
        ->between(
            CarbonImmutable::parse('2026-04-25T00:00:00Z'),
            CarbonImmutable::parse('2026-04-26T00:00:00Z'),
        )
        ->count();

    expect($count)->toBe(1);
});
