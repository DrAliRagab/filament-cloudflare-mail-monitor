<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor;

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
            ->hasMigration('create_cloudflare_mail_monitor_events_table');
    }
}
