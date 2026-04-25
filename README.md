# Cloudflare Mail Monitor for Filament

Cloudflare Mail Monitor for Filament stores and visualizes outbound Cloudflare Email Service activity inside Laravel and Filament.

This package targets Cloudflare Email Service outbound sending only. It fetches Email Sending analytics from Cloudflare's GraphQL Analytics API, stores normalized events in your database, and exposes Filament 5 pages and widgets for monitoring delivery, failures, authentication health, and recent logs.

See [`docs/implementation-plan.md`](docs/implementation-plan.md) for the revised build plan, current scope, and missing-part checklist.

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- Filament 5
- Cloudflare Email Service enabled for outbound sending
- Cloudflare API token with `Analytics Read` access for the configured zones

## Installation

```bash
composer require draliragab/filament-cloudflare-mail-monitor

php artisan vendor:publish --tag="cloudflare-mail-monitor-config"
php artisan vendor:publish --tag="cloudflare-mail-monitor-migrations"
php artisan migrate
```

## Configuration

Set the required environment variables:

```env
CLOUDFLARE_MAIL_MONITOR_API_TOKEN=your-cloudflare-api-token
CLOUDFLARE_MAIL_MONITOR_ZONE_ID=your-zone-id
CLOUDFLARE_MAIL_MONITOR_ZONE_NAME=example.com
```

Register the plugin in your Filament panel provider:

```php
use DrAliRagab\FilamentCloudflareMailMonitor\CloudflareMailMonitorPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(CloudflareMailMonitorPlugin::make());
}
```

The plugin registers:

- A Cloudflare Mail Monitor dashboard page with a manual refresh action.
- An Email Logs resource backed by stored Cloudflare events.
- A stats widget for total events, delivered events, failed events, and spam/NDR signals.

## Scheduling

The package ships a daily fetch command. Add it to your Laravel scheduler:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('cloudflare-mail-monitor:fetch')->daily();
Schedule::command('cloudflare-mail-monitor:prune')->daily();
```

Records older than 90 days are pruned by default. Publish the config to customize retention and every other package option.

## Cloudflare Notes

Cloudflare Email Service is currently evolving. This package uses the documented GraphQL Analytics datasets for outbound sending:

- `emailSendingAdaptiveGroups`
- `emailSendingAdaptive`

Cloudflare currently documents a 31-day analytics retention window, so scheduled fetching should run at least monthly. Daily fetching is recommended.
