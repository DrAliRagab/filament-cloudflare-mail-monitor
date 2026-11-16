<?php

declare(strict_types=1);

use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;

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

it('resolves account environment configuration with the documented precedence', function (?string $specific, ?string $generic, ?string $expected): void {
    $repositoryRepository = Env::getRepository();
    $names = ['CLOUDFLARE_MAIL_MONITOR_ACCOUNT_ID', 'CLOUDFLARE_ACCOUNT_ID'];
    $original = array_map($repositoryRepository->get(...), $names);
    try {
        foreach (array_combine($names, [$specific, $generic]) as $name => $value) {
            $value === null ? $repositoryRepository->clear($name) : $repositoryRepository->set($name, $value);
        }

        $config = require __DIR__.'/../../config/cloudflare-mail-monitor.php';
        expect($config['account_id'])->toBe($expected);
    } finally {
        foreach (array_combine($names, $original) as $name => $value) {
            $value === null ? $repositoryRepository->clear($name) : $repositoryRepository->set($name, $value);
        }
    }
})->with([
    ['specific', 'generic', 'specific'],
    [null, 'generic', 'generic'],
    ['', 'generic', 'generic'],
    [null, null, null],
]);

it('upgrades and rolls back suppression metadata without losing historical rows', function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    $migration = require __DIR__.'/../../database/migrations/update_cloudflare_mail_monitor_suppressions_scope_columns.php';
    $migration->down();
    DB::table('cloudflare_mail_monitor_suppressions')->insert([
        'zone_id' => 'legacy', 'suppression_id' => 'legacy', 'email' => 'person@example.com',
        'reason' => 'manual', 'suppressed_at' => '2026-01-01 00:00:00',
    ]);
    $migration->up();
    $row = CloudflareMailSuppression::query()->firstOrFail();
    expect($row->scope_type)->toBeNull()->and($row->read_only)->toBeNull()->and($row->email)->toBe('person@example.com');
    $migration->down();
    expect(DB::table('cloudflare_mail_monitor_suppressions')->count())->toBe(1);
    $migration->up();
});
