<?php

declare(strict_types=1);

use DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\CloudflareGraphqlClient;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\MissingCloudflareConfiguration;
use Illuminate\Support\Facades\Http;

it('sends GraphQL requests with the configured bearer token', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.api.base_url', 'https://api.example.test/client/v4');

    Http::fake([
        'api.example.test/client/v4/graphql' => Http::response([
            'data' => ['viewer' => ['zones' => []]],
        ]),
    ]);

    $data = app(CloudflareGraphqlClient::class)->query('query Test { viewer { zones { id } } }');

    expect($data)->toBe(['viewer' => ['zones' => []]]);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('fails when the token is not configured', function (): void {
    config()->set('cloudflare-mail-monitor.api.token');

    app(CloudflareGraphqlClient::class)->query('query Test { viewer { zones { id } } }');
})->throws(MissingCloudflareConfiguration::class, 'Cloudflare API token is not configured.');

it('does not leak tokens in HTTP failure messages', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response(['error' => 'nope'], 403),
    ]);

    app(CloudflareGraphqlClient::class)->query('query Test { viewer { zones { id } } }');
})->throws(CloudflareApiException::class, 'Cloudflare API request failed with HTTP status 403.');

it('surfaces GraphQL errors', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'errors' => [
                ['message' => 'Analytics Read permission is required.'],
            ],
        ]),
    ]);

    app(CloudflareGraphqlClient::class)->query('query Test { viewer { zones { id } } }');
})->throws(CloudflareApiException::class, 'Analytics Read permission is required.');

it('fails when Cloudflare returns a non-array payload', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response('not-json'),
    ]);

    app(CloudflareGraphqlClient::class)->query('query Test { viewer { zones { id } } }');
})->throws(CloudflareApiException::class, 'Cloudflare API returned a malformed GraphQL response.');

it('fails when Cloudflare response does not include data', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response(['messages' => []]),
    ]);

    app(CloudflareGraphqlClient::class)->query('query Test { viewer { zones { id } } }');
})->throws(CloudflareApiException::class, 'Cloudflare API returned a malformed GraphQL response.');

it('handles non-string GraphQL error messages safely', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'errors' => [
                ['message' => 123],
                ['extensions' => ['code' => 'unknown']],
            ],
        ]),
    ]);

    app(CloudflareGraphqlClient::class)->query('query Test { viewer { zones { id } } }');
})->throws(CloudflareApiException::class, '123; Unknown GraphQL error');
