<?php

declare(strict_types=1);

use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;

it('detects authorization failures from status codes and messages', function (): void {
    expect(new CloudflareApiException('Cloudflare API request failed with HTTP status 403.', 403)->isAuthorizationFailure())->toBeTrue()
        ->and(new CloudflareApiException('Cloudflare permission denied.')->isAuthorizationFailure())->toBeTrue()
        ->and(new CloudflareApiException('Cloudflare API request failed with HTTP status 500.', 500)->isAuthorizationFailure())->toBeFalse()
        ->and(new CloudflareApiException('Cloudflare API request failed with HTTP status 500.')->statusCode())->toBeNull();
});
