<?php

declare(strict_types=1);

use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;

it('loads default package configuration', function (): void {
    expect(config('cloudflare-mail-monitor.retention.days'))->toBe(90)
        ->and(config('cloudflare-mail-monitor.fetch.max_lookback_days'))->toBe(31)
        ->and(config('cloudflare-mail-monitor.filament.navigation_group'))->toBe('Cloudflare');
});

it('returns only configured zones with an id', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
        ['id' => null, 'name' => 'missing.test'],
        'invalid',
    ]);

    expect(Config::zones())->toBe([
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);
});
