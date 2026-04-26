<?php

declare(strict_types=1);

use DrAliRagab\FilamentCloudflareMailMonitor\Events\CloudflareMailSuppressionCreated;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\MissingCloudflareConfiguration;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSuppressionFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.suppressions.page_size', 2);
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
        ['id' => 'zone-2', 'name' => 'example.net'],
    ]);
});

it('fetches email sending suppressions for configured zones', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::sequence()
            ->push([
                'page' => 1,
                'per_page' => 2,
                'total' => 3,
                'result' => [
                    [
                        'id' => 'suppression-1',
                        'email' => 'one@example.com',
                        'reason' => 'hard_bounce',
                        'created_at' => '2026-04-25T10:00:00Z',
                        'expires_at' => null,
                        'zones' => ['example.com'],
                    ],
                    [
                        'id' => 'suppression-2',
                        'email' => 'two@example.com',
                        'reason' => 'spam_complaint',
                        'created_at' => '2026-04-25T11:00:00Z',
                        'expires_at' => '2026-05-25T11:00:00Z',
                        'zones' => ['example.com'],
                    ],
                ],
            ])
            ->push([
                'page' => 2,
                'per_page' => 2,
                'total' => 3,
                'result' => [[
                    'id' => 'suppression-3',
                    'email' => 'three@example.com',
                    'reason' => 'manual',
                    'created_at' => '2026-04-25T12:00:00Z',
                    'expires_at' => null,
                    'zones' => ['example.com'],
                ]],
            ]),
        'api.cloudflare.com/client/v4/zones/zone-2/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 2,
            'total' => 1,
            'result' => [[
                'id' => 'suppression-4',
                'email' => 'four@example.net',
                'reason' => 'hard_bounce',
                'created_at' => '2026-04-26T10:00:00Z',
                'expires_at' => null,
                'zones' => ['example.net'],
            ]],
        ]),
    ]);

    $stored = app(CloudflareMailSuppressionFetcher::class)->fetch();

    expect($stored)->toBe(4)
        ->and(CloudflareMailSuppression::query()->count())->toBe(4)
        ->and(CloudflareMailSuppression::query()->where('suppression_id', 'suppression-1')->value('email'))->toBe('one@example.com')
        ->and(CloudflareMailSuppression::query()->where('suppression_id', 'suppression-1')->value('zone_id'))->toBe('zone-1')
        ->and(CloudflareMailSuppression::query()->where('suppression_id', 'suppression-2')->first()?->expires_at?->toIso8601String())->toBe('2026-05-25T11:00:00+00:00')
        ->and(CloudflareMailSuppression::query()->where('suppression_id', 'suppression-4')->value('zone_name'))->toBe('example.net');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/zones/zone-1/email/sending/suppression')
        && str_contains($request->url(), 'page=1')
        && str_contains($request->url(), 'per_page=2')
        && str_contains($request->url(), 'order=created_at')
        && str_contains($request->url(), 'direction=desc')
        && $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('ignores malformed suppression entries', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 100,
            'total' => 1,
            'result' => [
                'not-a-suppression',
                [
                    'id' => 'suppression-1',
                    'email' => 'one@example.com',
                    'reason' => 'hard_bounce',
                    'created_at' => '2026-04-25T10:00:00Z',
                    'expires_at' => null,
                    'zones' => 'not-a-list',
                ],
            ],
        ]),
    ]);

    $stored = app(CloudflareMailSuppressionFetcher::class)->fetch();

    expect($stored)->toBe(1)
        ->and(CloudflareMailSuppression::query()->first()?->cloudflare_zones)->toBe([]);
});

it('uses fallback pagination totals when Cloudflare omits totals', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 2,
            'result' => [[
                'id' => 'suppression-1',
                'email' => 'one@example.com',
                'reason' => 'hard_bounce',
                'created_at' => '2026-04-25T10:00:00Z',
                'expires_at' => null,
                'zones' => ['example.com'],
            ]],
        ]),
    ]);

    expect(app(CloudflareMailSuppressionFetcher::class)->fetch())->toBe(1);
});

it('normalizes string pagination totals from Cloudflare', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 2,
            'total' => '1',
            'result' => [[
                'id' => 'suppression-1',
                'email' => 'one@example.com',
                'reason' => 'hard_bounce',
                'created_at' => '2026-04-25T10:00:00Z',
                'expires_at' => null,
                'zones' => ['example.com'],
            ]],
        ]),
    ]);

    expect(app(CloudflareMailSuppressionFetcher::class)->fetch())->toBe(1);
});

it('dispatches an event only when a suppression is newly stored', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 2,
            'total' => 1,
            'result' => [[
                'id' => 'suppression-1',
                'email' => 'one@example.com',
                'reason' => 'hard_bounce',
                'created_at' => '2026-04-25T10:00:00Z',
                'expires_at' => null,
                'zones' => ['example.com'],
            ]],
        ]),
    ]);

    Event::fake([CloudflareMailSuppressionCreated::class]);

    expect(app(CloudflareMailSuppressionFetcher::class)->fetch())->toBe(1);

    Event::assertDispatched(
        CloudflareMailSuppressionCreated::class,
        fn (CloudflareMailSuppressionCreated $cloudflareMailSuppressionCreated): bool => $cloudflareMailSuppressionCreated->suppression->suppression_id === 'suppression-1'
            && $cloudflareMailSuppressionCreated->suppression->email === 'one@example.com'
    );
    Event::assertDispatchedTimes(CloudflareMailSuppressionCreated::class);

    Event::fake([CloudflareMailSuppressionCreated::class]);

    expect(app(CloudflareMailSuppressionFetcher::class)->fetch())->toBe(1);

    Event::assertNotDispatched(CloudflareMailSuppressionCreated::class);
});

it('fails safely when Cloudflare rejects a suppression request', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response(['success' => false], 403),
    ]);

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(CloudflareApiException::class, 'Cloudflare API request failed with HTTP status 403.');

it('fails safely when Cloudflare returns a non-array suppression response', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response('not-json'),
    ]);

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(CloudflareApiException::class, 'Cloudflare API returned a malformed REST response.');

it('fails when suppression API payload is malformed', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 100,
            'total' => 1,
            'result' => 'not-a-list',
        ]),
    ]);

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(CloudflareApiException::class, 'Cloudflare API returned a malformed REST response.');

it('fails when the token is not configured for suppression fetching', function (): void {
    config()->set('cloudflare-mail-monitor.api.token');

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(MissingCloudflareConfiguration::class, 'Cloudflare API token is not configured.');
