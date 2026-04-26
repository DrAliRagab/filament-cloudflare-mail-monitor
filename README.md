# Cloudflare Mail Monitor for Filament

Monitor outbound Cloudflare Email Service activity in Laravel and Filament.

This package syncs outbound Email Sending analytics and zone-scoped suppression lists from Cloudflare, stores normalized records locally, and provides Filament 5 pages, resources, and widgets for delivery monitoring, failures, authentication health, recent logs, and suppressions.

## Features

- Sync outbound email events into your database
- View searchable email logs in Filament
- Sync zone-scoped Email Sending suppressions into your database
- View searchable suppression records in Filament
- Inspect delivery failures and authentication health
- Refresh logs manually from the dashboard, list, or detail views
- Prune old records with Laravel `model:prune`
- Backfill recent history on the first run, then keep syncing daily

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- Filament 5
- Cloudflare Email Service enabled for outbound sending
- Cloudflare API token with `Analytics Read` and Email Sending suppression read access for the configured zones

## Installation

```bash
composer require draliragab/filament-cloudflare-mail-monitor

php artisan vendor:publish --tag="cloudflare-mail-monitor-config"
php artisan vendor:publish --tag="cloudflare-mail-monitor-migrations"
php artisan migrate
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

## Configuration

Set the required environment variables:

```env
CLOUDFLARE_MAIL_MONITOR_API_TOKEN=your-cloudflare-api-token
CLOUDFLARE_MAIL_MONITOR_ZONE_ID=your-zone-id
CLOUDFLARE_MAIL_MONITOR_ZONE_NAME=example.com
```

Optional settings include retention, fetch lookback, privacy masking, and Filament navigation labels/icons.
Use `CLOUDFLARE_MAIL_MONITOR_SUPPRESSIONS_PAGE_SIZE` to tune suppression API pagination, up to Cloudflare's documented maximum of 1000.

## Scheduling

Add the fetch and prune commands to your Laravel scheduler:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('cloudflare-mail-monitor:fetch')->daily();
Schedule::command('cloudflare-mail-monitor:prune')->daily();
```

After installation, run a one-time backfill with a longer lookback, then let the daily schedule keep syncing new events and suppressions:

```bash
php artisan cloudflare-mail-monitor:fetch --days=30
```

Event records older than 90 days are pruned by default. Suppression records are pruned after their `expires_at` date. Publish the config to customize retention and other package options.

## Commands

Fetch recent Cloudflare email events and zone suppressions synchronously:

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

Prune old records immediately:

```bash
php artisan cloudflare-mail-monitor:prune
```

## What It Provides

The plugin registers:

- A Cloudflare Mail Monitor dashboard page with a manual refresh action
- An Email Suppressions resource backed by stored Cloudflare suppression records
- An Email Logs resource backed by stored Cloudflare events
- A stats widget for total events, delivered events, failed events, and spam/NDR signals
- A delivery failure analytics widget for rejected/NDR events and top failure causes
- An authentication health widget for DKIM, DMARC, and SPF failure counts

The Email Logs resource includes filters for configured zones, delivery status, DKIM/DMARC/SPF results, spam/NDR flags, and occurred-at date ranges.

You can disable plugin parts fluently if your panel only needs some screens:

```php
CloudflareMailMonitorPlugin::make()
    ->dashboard()
    ->suppressionsResource()
    ->logsResource()
    ->statsWidget();
```

Pass `false` to any of these methods to disable that part.

## Events

The package dispatches `CloudflareMailSuppressionCreated` after a new suppression row is committed locally. Existing suppression rows updated during later syncs do not dispatch the event again.

```php
use DrAliRagab\FilamentCloudflareMailMonitor\Events\CloudflareMailSuppressionCreated;
use Illuminate\Support\Facades\Event;

Event::listen(CloudflareMailSuppressionCreated::class, function (CloudflareMailSuppressionCreated $event): void {
    $suppression = $event->suppression;

    // Sync your local allow/block list, notify a team, or update another system.
});
```

## Programmatic Metrics

The package stores individual events locally through `CloudflareMailEventFetcher`. It also exposes `CloudflareMailAggregateFetcher` for on-demand aggregate status counts from Cloudflare's `emailSendingAdaptiveGroups` dataset.

```php
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailAggregateFetcher;

$metrics = app(CloudflareMailAggregateFetcher::class)->statusCounts();
```

Aggregate metrics are returned as `EmailAggregateMetricData` objects and are not persisted by default.

## Cloudflare Notes

Cloudflare Email Service is evolving. This package uses the documented GraphQL Analytics datasets for outbound sending:

- `emailSendingAdaptiveGroups`
- `emailSendingAdaptive`

The Email Suppressions resource is populated by the existing `cloudflare-mail-monitor:fetch` command using the zone-scoped REST endpoint `GET /zones/{zone_id}/email/sending/suppression` for each configured zone. It does not require a Cloudflare account ID.

Cloudflare currently documents a 31-day analytics retention window, so scheduled fetching should run at least monthly. Daily fetching is recommended.

## Troubleshooting

- `Cloudflare API token is not configured`: set `CLOUDFLARE_MAIL_MONITOR_API_TOKEN` and clear cached config with `php artisan config:clear`.
- Empty dashboard or logs: confirm `CLOUDFLARE_MAIL_MONITOR_ZONE_ID` is a zone ID, not an account ID, and that the token has `Analytics Read` for that zone.
- Empty suppressions resource: run `php artisan cloudflare-mail-monitor:fetch`, confirm the zone has Email Sending enabled, and confirm the token can read Email Sending suppressions for that zone.
- Missing older events: Cloudflare currently documents a 31-day retention window for Email Service analytics; schedule daily fetching to preserve events locally for longer retention.
- GraphQL errors from Cloudflare: verify Email Service is enabled for the sending domain and that the configured zone has outbound sending activity.
- Slow or timed-out fetches: lower `CLOUDFLARE_MAIL_MONITOR_FETCH_PAGE_SIZE` or increase `CLOUDFLARE_MAIL_MONITOR_API_TIMEOUT` in the published config.
