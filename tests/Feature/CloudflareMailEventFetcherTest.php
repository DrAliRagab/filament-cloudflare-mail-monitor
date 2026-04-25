<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Jobs\FetchCloudflareMailEvents;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailEventFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.fetch.page_size', 50);
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);
});

it('fetches Cloudflare events and upserts them by event hash', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => [[
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
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    $cloudflareMailEventFetcher = app(CloudflareMailEventFetcher::class);

    expect($cloudflareMailEventFetcher->fetch())->toBe(1)
        ->and($cloudflareMailEventFetcher->fetch())->toBe(1)
        ->and(CloudflareMailEvent::query()->count())->toBe(1)
        ->and(CloudflareMailEvent::query()->first()?->zone_name)->toBe('example.com');
});

it('paginates through all available email events', function (): void {
    $requestCount = 0;

    Http::fake(function (Request $request) use (&$requestCount) {
        ++$requestCount;

        $pageOne = array_map(static function (int $index): array {
            $minute = str_pad((string) (49 - $index), 2, '0', STR_PAD_LEFT);

            return [
                'datetime' => sprintf('2026-04-25T10:%s:00Z', $minute),
                'messageId' => 'message-'.$index,
            ];
        }, range(0, 49));

        $pageTwo = array_map(static function (int $index): array {
            $minute = str_pad((string) (99 - $index), 2, '0', STR_PAD_LEFT);

            return [
                'datetime' => sprintf('2026-04-25T09:%s:00Z', $minute),
                'messageId' => 'message-'.$index,
            ];
        }, range(50, 99));

        return match ($requestCount) {
            1 => Http::response([
                'data' => [
                    'viewer' => [
                        'zones' => [[
                            'emailSendingAdaptive' => $pageOne,
                        ]],
                    ],
                ],
            ]),
            2 => Http::response([
                'data' => [
                    'viewer' => [
                        'zones' => [[
                            'emailSendingAdaptive' => $pageTwo,
                        ]],
                    ],
                ],
            ]),
            default => Http::response([
                'data' => [
                    'viewer' => [
                        'zones' => [[
                            'emailSendingAdaptive' => [],
                        ]],
                    ],
                ],
            ]),
        };
    });

    expect(app(CloudflareMailEventFetcher::class)->fetch())->toBe(100)
        ->and(CloudflareMailEvent::query()->count())->toBe(100);

    Http::assertSentCount(3);

    $requests = Http::recorded();

    expect($requests[0][0]->data()['variables']['datetimeBefore'] ?? null)->toBeNull()
        ->and($requests[1][0]->data()['variables']['datetimeBefore'] ?? null)->toBe('2026-04-25T10:00:00+00:00')
        ->and($requests[2][0]->data()['variables']['datetimeBefore'] ?? null)->toBe('2026-04-25T09:00:00+00:00');
});

it('ignores malformed event lists without failing the whole fetch', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => 'not-a-list',
                    ]],
                ],
            ],
        ]),
    ]);

    expect(app(CloudflareMailEventFetcher::class)->fetch())->toBe(0)
        ->and(CloudflareMailEvent::query()->count())->toBe(0);
});

it('skips malformed items inside Cloudflare event lists', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => [
                            'not-an-event',
                            ['datetime' => '2026-04-25T10:15:00Z', 'messageId' => 'valid-event'],
                        ],
                    ]],
                ],
            ],
        ]),
    ]);

    expect(app(CloudflareMailEventFetcher::class)->fetch())->toBe(1)
        ->and(CloudflareMailEvent::query()->where('message_id', 'valid-event')->exists())->toBeTrue();
});

it('fetch command rejects invalid lookback days', function (): void {
    $this->artisan('cloudflare-mail-monitor:fetch', ['--days' => 0])
        ->assertFailed();
});

it('fetch command can dispatch a queued fetch job', function (): void {
    Queue::fake();

    $this->artisan('cloudflare-mail-monitor:fetch', ['--days' => 2, '--queue' => true])
        ->assertSuccessful();

    Queue::assertPushed(FetchCloudflareMailEvents::class, fn (FetchCloudflareMailEvents $fetchCloudflareMailEvents): bool => $fetchCloudflareMailEvents->lookbackDays === 2);
});

it('fetch command stores events synchronously', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => [[
                            'datetime' => '2026-04-25T10:15:00Z',
                            'messageId' => 'message-command',
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    $this->artisan('cloudflare-mail-monitor:fetch')
        ->assertSuccessful();

    expect(CloudflareMailEvent::query()->where('message_id', 'message-command')->exists())->toBeTrue();
});

it('fetch job stores events with a requested lookback range', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => [[
                            'datetime' => '2026-04-25T10:15:00Z',
                            'messageId' => 'message-job',
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    app(FetchCloudflareMailEvents::class, ['lookbackDays' => 2])
        ->handle(app(CloudflareMailEventFetcher::class));

    expect(CloudflareMailEvent::query()->where('message_id', 'message-job')->exists())->toBeTrue();
});

it('fetch job stores events with the default configured range', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => [[
                            'datetime' => '2026-04-25T10:15:00Z',
                            'messageId' => 'message-job-default',
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    app(FetchCloudflareMailEvents::class)
        ->handle(app(CloudflareMailEventFetcher::class));

    expect(CloudflareMailEvent::query()->where('message_id', 'message-job-default')->exists())->toBeTrue();
});

it('prunes events older than configured retention', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-25T00:00:00Z'));

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('a', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2025-12-01T00:00:00Z'),
    ]);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('b', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-01T00:00:00Z'),
    ]);

    Artisan::call('model:prune', ['--model' => [CloudflareMailEvent::class]]);

    expect(CloudflareMailEvent::query()->pluck('event_hash')->all())->toBe([str_repeat('b', 64)]);

    CarbonImmutable::setTestNow();
});

it('uses configured retention for pruning', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-25T00:00:00Z'));
    config()->set('cloudflare-mail-monitor.retention.days', 10);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('c', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-01T00:00:00Z'),
    ]);

    Artisan::call('model:prune', ['--model' => [CloudflareMailEvent::class]]);

    expect(CloudflareMailEvent::query()->count())->toBe(0);

    CarbonImmutable::setTestNow();
});

it('prunes events through the package prune command', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-25T00:00:00Z'));

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('d', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2025-12-01T00:00:00Z'),
    ]);

    expect(Artisan::call('cloudflare-mail-monitor:prune'))->toBe(0);

    expect(CloudflareMailEvent::query()->count())->toBe(0);

    CarbonImmutable::setTestNow();
});
