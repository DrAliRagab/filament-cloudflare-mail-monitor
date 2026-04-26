<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\ConfiguredZone;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailEventData;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailSuppressionData;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
});

it('persists normalized Cloudflare email event data', function (): void {
    $emailEventData = EmailEventData::fromCloudflare(
        configuredZone: new ConfiguredZone('zone-1', 'example.com'),
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

    $cloudflareMailEvent = CloudflareMailEvent::query()->create($emailEventData->toDatabaseAttributes());

    expect($cloudflareMailEvent->event_hash)->toHaveLength(64)
        ->and($cloudflareMailEvent->occurred_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($cloudflareMailEvent->is_spam)->toBeFalse()
        ->and($cloudflareMailEvent->raw)->toHaveKey('messageId', 'message-1');
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

it('persists normalized Cloudflare suppression data', function (): void {
    $emailSuppressionData = EmailSuppressionData::fromCloudflare(
        configuredZone: new ConfiguredZone('zone-1', 'example.com'),
        payload: [
            'id' => 'suppression-1',
            'email' => 'person@example.com',
            'reason' => 'hard_bounce',
            'created_at' => '2026-04-25T10:15:00Z',
            'expires_at' => '2026-05-25T10:15:00Z',
            'zones' => ['example.com'],
        ],
    );

    $cloudflareMailSuppression = CloudflareMailSuppression::query()->create($emailSuppressionData->toDatabaseAttributes());

    expect($cloudflareMailSuppression->suppression_id)->toBe('suppression-1')
        ->and($cloudflareMailSuppression->suppressed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($cloudflareMailSuppression->expires_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($cloudflareMailSuppression->cloudflare_zones)->toBe(['example.com'])
        ->and($cloudflareMailSuppression->raw)->toHaveKey('id', 'suppression-1');
});

it('can scope suppressions by zone', function (): void {
    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'suppression-1',
        'zone_id' => 'zone-1',
        'email' => 'one@example.com',
        'reason' => 'hard_bounce',
        'suppressed_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
    ]);

    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'suppression-2',
        'zone_id' => 'zone-2',
        'email' => 'two@example.com',
        'reason' => 'manual',
        'suppressed_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
    ]);

    expect(CloudflareMailSuppression::query()->forZone('zone-1')->count())->toBe(1);
});

it('prunes suppressions after their expiration date', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-25T00:00:00Z'));

    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'expired',
        'zone_id' => 'zone-1',
        'email' => 'expired@example.com',
        'reason' => 'manual',
        'suppressed_at' => CarbonImmutable::parse('2026-04-01T00:00:00Z'),
        'expires_at' => CarbonImmutable::parse('2026-04-24T00:00:00Z'),
    ]);

    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'active',
        'zone_id' => 'zone-1',
        'email' => 'active@example.com',
        'reason' => 'hard_bounce',
        'suppressed_at' => CarbonImmutable::parse('2026-04-01T00:00:00Z'),
        'expires_at' => null,
    ]);

    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'future',
        'zone_id' => 'zone-1',
        'email' => 'future@example.com',
        'reason' => 'manual',
        'suppressed_at' => CarbonImmutable::parse('2026-04-01T00:00:00Z'),
        'expires_at' => CarbonImmutable::parse('2026-04-26T00:00:00Z'),
    ]);

    Artisan::call('model:prune', ['--model' => [CloudflareMailSuppression::class]]);

    expect(CloudflareMailSuppression::query()->pluck('suppression_id')->all())->toBe(['active', 'future']);

    CarbonImmutable::setTestNow();
});
