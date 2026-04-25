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
- A delivery failure analytics widget for rejected/NDR events and top failure causes.
- An authentication health widget for DKIM, DMARC, and SPF failure counts.

The Email Logs resource includes filters for configured zones, delivery status, DKIM/DMARC/SPF results, spam/NDR flags, and occurred-at date ranges.

You can disable plugin parts fluently if your panel only needs some screens:

```php
CloudflareMailMonitorPlugin::make()
    ->dashboard()
    ->logsResource()
    ->statsWidget();
```

Pass `false` to any of these methods to disable that part.

## Scheduling

The package ships a daily fetch command. Add it to your Laravel scheduler:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('cloudflare-mail-monitor:fetch')->daily();
Schedule::command('cloudflare-mail-monitor:prune')->daily();
```

Records older than 90 days are pruned by default. Publish the config to customize retention and every other package option.

## Commands

Fetch recent Cloudflare email events synchronously:

```bash
php artisan cloudflare-mail-monitor:fetch
```

Fetch a specific lookback window:

```bash
php artisan cloudflare-mail-monitor:fetch --days=7
```

Dispatch the fetch through your queue:

```bash
php artisan cloudflare-mail-monitor:fetch --queue
```

## Programmatic Metrics

The package stores individual events locally through `CloudflareMailEventFetcher`. It also exposes `CloudflareMailAggregateFetcher` for on-demand aggregate status counts from Cloudflare's `emailSendingAdaptiveGroups` dataset.

```php
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailAggregateFetcher;

$metrics = app(CloudflareMailAggregateFetcher::class)->statusCounts();
```

Aggregate metrics are returned as `EmailAggregateMetricData` objects and are not persisted by default.

## Cloudflare Notes

Cloudflare Email Service is currently evolving. This package uses the documented GraphQL Analytics datasets for outbound sending:

- `emailSendingAdaptiveGroups`
- `emailSendingAdaptive`

Cloudflare currently documents a 31-day analytics retention window, so scheduled fetching should run at least monthly. Daily fetching is recommended.

## Screenshots

Screenshots should be added before publishing the package release:

- Dashboard with overview, failure analytics, and authentication health widgets.
- Email Logs resource with zone, date range, status, and authentication filters.

## Troubleshooting

- `Cloudflare API token is not configured`: set `CLOUDFLARE_MAIL_MONITOR_API_TOKEN` and clear cached config with `php artisan config:clear`.
- Empty dashboard or logs: confirm `CLOUDFLARE_MAIL_MONITOR_ZONE_ID` is a zone ID, not an account ID, and that the token has `Analytics Read` for that zone.
- Missing older events: Cloudflare currently documents a 31-day retention window for Email Service analytics; schedule daily fetching to preserve events locally for longer retention.
- GraphQL errors from Cloudflare: verify Email Service is enabled for the sending domain and that the configured zone has outbound sending activity.
- Slow or timed-out fetches: lower `CLOUDFLARE_MAIL_MONITOR_FETCH_PAGE_SIZE` or increase `CLOUDFLARE_MAIL_MONITOR_API_TIMEOUT` in the published config.
