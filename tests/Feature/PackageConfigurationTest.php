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
        ['id' => '', 'name' => 'empty.test'],
        'invalid',
    ]);

    expect(Config::zones())->toBe([
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);
});

it('normalizes scalar config values and falls back for unsupported values', function (): void {
    config()->set('cloudflare-mail-monitor.example.string', 123);
    config()->set('cloudflare-mail-monitor.example.invalid_string', ['nope']);
    config()->set('cloudflare-mail-monitor.example.integer', '15');
    config()->set('cloudflare-mail-monitor.example.invalid_integer', ['nope']);
    config()->set('cloudflare-mail-monitor.example.boolean', true);
    config()->set('cloudflare-mail-monitor.example.invalid_boolean', 'yes');

    expect(Config::string('example.string'))->toBe('123')
        ->and(Config::string('example.invalid_string', 'fallback'))->toBe('fallback')
        ->and(Config::integer('example.integer', 1))->toBe(15)
        ->and(Config::integer('example.invalid_integer', 1))->toBe(1)
        ->and(Config::boolean('example.boolean'))->toBeTrue()
        ->and(Config::boolean('example.invalid_boolean'))->toBeFalse();
});

it('returns no zones when zones config is not an array', function (): void {
    config()->set('cloudflare-mail-monitor.zones', 'invalid');

    expect(Config::zones())->toBe([]);
});
