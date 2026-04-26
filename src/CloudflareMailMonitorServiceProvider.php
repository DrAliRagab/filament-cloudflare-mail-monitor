<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor;

use DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare\CloudflareGraphqlClient;
use DrAliRagab\FilamentCloudflareMailMonitor\Commands\FetchCloudflareMailEventsCommand;
use DrAliRagab\FilamentCloudflareMailMonitor\Commands\PruneCloudflareMailEventsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CloudflareMailMonitorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'cloudflare-mail-monitor';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasViews('filament-cloudflare-mail-monitor')
            ->hasMigrations([
                'create_cloudflare_mail_monitor_events_table',
                'create_cloudflare_mail_monitor_suppressions_table',
            ])
            ->hasCommands([
                FetchCloudflareMailEventsCommand::class,
                PruneCloudflareMailEventsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CloudflareGraphqlClient::class);
    }
}
