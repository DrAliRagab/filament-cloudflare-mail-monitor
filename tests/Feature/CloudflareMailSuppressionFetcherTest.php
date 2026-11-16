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
    config()->set('cloudflare-mail-monitor.account_id', 'account-1');
    config()->set('cloudflare-mail-monitor.suppressions.page_size', 2);
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
        ['id' => 'zone-2', 'name' => 'example.net'],
    ]);
});

it('paginates an account once and associates scopes with configured zones', function (): void {
    Http::preventStrayRequests();
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'EXAMPLE.COM.'],
        ['id' => 'zone-2', 'name' => 'example.net'],
        ['id' => 'zone-3', 'name' => 'mail.example.com'],
    ]);
    $entry = static fn (string $id, array $scope): array => [
        'id' => $id, 'email' => 'person@example.org', 'reason' => 'hard_bounce',
        'created_at' => '2026-11-12T14:06:42Z', 'expires_at' => null,
        'read_only' => true, 'scope' => $scope,
    ];
    $domain = static fn (string $name): array => ['type' => 'sending_domain', 'value' => $name];
    Http::fake(['api.cloudflare.com/client/v4/accounts/account-1/email/sending/suppressions*' => Http::sequence()
        ->push(['result' => [$entry('root', $domain('example.com'))], 'result_info' => ['next_cursor' => 'first']])
        ->push(['result' => [], 'result_info' => ['next_cursor' => 'second']])
        ->push(['result' => [
            $entry('subdomain', $domain('news.example.com')),
            $entry('specific', $domain('mail.example.com')),
            $entry('unrelated', $domain('notexample.com')),
            $entry('account', ['type' => 'account']),
        ], 'result_info' => ['next_cursor' => null]])]);

    expect(app(CloudflareMailSuppressionFetcher::class)->fetch())->toBe(6);
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => ($request['cursor'] ?? null) === 'second' && (int) $request['per_page'] === 2);
    expect(CloudflareMailSuppression::query()->where('suppression_id', 'unrelated')->exists())->toBeFalse()
        ->and(CloudflareMailSuppression::query()->where('suppression_id', 'specific')->value('zone_id'))->toBe('zone-3')
        ->and(CloudflareMailSuppression::query()->where('suppression_id', 'subdomain')->value('zone_id'))->toBe('zone-1');
    $record = CloudflareMailSuppression::query()->where('suppression_id', 'account')->firstOrFail();
    expect($record->cloudflare_zones)->toBe(['*'])->and($record->read_only)->toBeTrue()
        ->and($record->scope_type)->toBe('account')->and($record->scope_value)->toBeNull()
        ->and($record->raw['read_only'])->toBeTrue();
    $record = CloudflareMailSuppression::query()->where('suppression_id', 'root')->firstOrFail();
    expect($record->scope_value)->toBe('example.com')->and($record->cloudflare_zones)->toBe(['example.com']);
});

it('ignores malformed suppression entries', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-1/email/sending/suppressions*' => Http::response([
            'success' => true,
            'result_info' => ['next_cursor' => null],
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

    expect($stored)->toBe(0);
});

it('dispatches an event only when a suppression is newly stored', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-1/email/sending/suppressions*' => Http::response([
            'success' => true,
            'result_info' => ['next_cursor' => null],
            'result' => [[
                'id' => 'suppression-1',
                'email' => 'one@example.com',
                'reason' => 'hard_bounce',
                'created_at' => '2026-04-25T10:00:00Z',
                'expires_at' => null,
                'scope' => ['type' => 'sending_domain', 'value' => 'example.com'],
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
        'api.cloudflare.com/client/v4/accounts/account-1/email/sending/suppressions*' => Http::response(['success' => false], 403),
    ]);

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(CloudflareApiException::class, 'Cloudflare API request failed with HTTP status 403.');

it('fails safely when Cloudflare returns a non-array suppression response', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-1/email/sending/suppressions*' => Http::response('not-json'),
    ]);

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(CloudflareApiException::class, 'Cloudflare API returned a malformed REST response.');

it('fails when suppression API payload is malformed', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-1/email/sending/suppressions*' => Http::response([
            'success' => true,
            'result_info' => ['next_cursor' => null],
            'result' => 'not-a-list',
        ]),
    ]);

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(CloudflareApiException::class, 'Cloudflare API returned a malformed REST response.');

it('fails when the token is not configured for suppression fetching', function (): void {
    config()->set('cloudflare-mail-monitor.api.token');

    app(CloudflareMailSuppressionFetcher::class)->fetch();
})->throws(MissingCloudflareConfiguration::class, 'Cloudflare API token is not configured.');

it('rejects missing suppression configuration', function (string $key, mixed $value, string $message): void {
    config()->set('cloudflare-mail-monitor.'.$key, $value);
    Http::fake();
    expect(fn () => app(CloudflareMailSuppressionFetcher::class)->fetch())
        ->toThrow(MissingCloudflareConfiguration::class, $message);
    Http::assertNothingSent();
})->with([
    ['account_id', null, 'Cloudflare account ID is not configured'],
    ['account_id', ' ', 'Cloudflare account ID is not configured'],
    ['zones', [['id' => 'zone-1']], 'requires a domain name'],
]);

it('does nothing when no zones are configured', function (): void {
    config()->set('cloudflare-mail-monitor.zones', []);
    expect(app(CloudflareMailSuppressionFetcher::class)->fetch())->toBe(0);
});

it('rejects invalid or repeated pagination cursors', function (mixed $cursor): void {
    Http::fake(['*' => Http::sequence()
        ->push(['result' => [], 'result_info' => ['next_cursor' => 'again']])
        ->push(['result' => [], 'result_info' => ['next_cursor' => $cursor]])]);
    expect(fn () => app(CloudflareMailSuppressionFetcher::class)->fetch())->toThrow(CloudflareApiException::class);
    expect(CloudflareMailSuppression::query()->count())->toBe(0);
})->with(['again', 123]);

it('clamps page size and accepts an empty terminal cursor', function (int $size, int $expected): void {
    config()->set('cloudflare-mail-monitor.suppressions.page_size', $size);
    Http::fake(['*' => Http::response(['result' => [], 'result_info' => ['next_cursor' => '']])]);
    expect(app(CloudflareMailSuppressionFetcher::class)->fetch())->toBe(0);
    Http::assertSent(fn (Request $request): bool => (int) $request['per_page'] === $expected);
})->with([[0, 1], [2000, 1000]]);
