# Cloudflare Mail Monitor for Filament

Monitor outbound Cloudflare Email Service activity in Laravel and Filament.

This package syncs outbound Email Sending analytics and account suppression lists associated with configured zones from Cloudflare, stores normalized records locally, and provides Filament 5 pages, resources, and widgets for delivery monitoring, failures, authentication health, recent logs, and suppressions.

## Features

- Sync outbound email events into your database
- View searchable email logs in Filament
- Sync account-wide and sending-domain Email Sending suppressions into your database
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
- Cloudflare API token with `Analytics Read` for configured zones and **Account → Email Sending → Edit** for the account ([Cloudflare suppression API permissions](https://developers.cloudflare.com/email-service/configuration/suppressions/))

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
CLOUDFLARE_MAIL_MONITOR_ACCOUNT_ID=your-cloudflare-account-id
CLOUDFLARE_MAIL_MONITOR_ZONE_ID=your-zone-id
CLOUDFLARE_MAIL_MONITOR_ZONE_NAME=example.com
```

`CLOUDFLARE_MAIL_MONITOR_ACCOUNT_ID` falls back to `CLOUDFLARE_ACCOUNT_ID` when unset or empty. All configured zones must belong to this account. Suppression sync requires an account ID and a domain name (`zones[].name`, or `CLOUDFLARE_MAIL_MONITOR_ZONE_NAME` for the default zone); these are not discovered automatically. Zone names must be DNS domains, not display labels.

Account-owned tokens are verified with `GET /accounts/{account_id}/tokens/verify`, not `/user/tokens/verify` ([verification API](https://developers.cloudflare.com/api/resources/accounts/subresources/tokens/methods/verify/)). A failure at the user-token endpoint does not establish that an account-owned token is invalid.

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

Fetch recent Cloudflare email events and suppressions synchronously:

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

The Email Suppressions resource uses `GET /accounts/{account_id}/email/sending/suppressions` once per fetch, following cursor pagination across all pages. Account-wide suppressions are associated with every configured zone. Sending-domain suppressions are associated with the matching zone, including its subdomains; overlapping configured zones use the most specific match. Unrelated domains are excluded. The stored scope retains the exact domain affected: showing a subdomain suppression under its zone does not make it apply to the parent domain.

Scope and read-only status are displayed in the resource. Historical rows retain unknown scope until refreshed; sync does not delete records missing from the response. `Stored N` counts local zone associations, so an account-wide suppression can count more than once.

The synchronous command reports successful event imports before syncing suppressions. A suppression API error is reported through Laravel's exception handler and printed as an incomplete-sync warning, but the command returns success. Event, configuration, and database errors still fail. With `--queue`, suppression failures still trigger the job's existing retries and can fail the job.

Cloudflare currently documents a 31-day analytics retention window, so scheduled fetching should run at least monthly. Daily fetching is recommended.

## Troubleshooting

- `Cloudflare API token is not configured`: set `CLOUDFLARE_MAIL_MONITOR_API_TOKEN` and clear cached config with `php artisan config:clear`.
- Empty dashboard or logs: confirm `CLOUDFLARE_MAIL_MONITOR_ZONE_ID` is a zone ID, not an account ID, and that the token has `Analytics Read` for that zone.
- Empty suppressions resource: run `php artisan cloudflare-mail-monitor:fetch`, confirm the account ID and zone domain names are configured, and confirm the token has Account → Email Sending → Edit access.
- Missing older events: Cloudflare currently documents a 31-day retention window for Email Service analytics; schedule daily fetching to preserve events locally for longer retention.
- GraphQL errors from Cloudflare: verify Email Service is enabled for the sending domain and that the configured zone has outbound sending activity.
- Slow or timed-out fetches: lower `CLOUDFLARE_MAIL_MONITOR_FETCH_PAGE_SIZE` or increase `CLOUDFLARE_MAIL_MONITOR_API_TIMEOUT` in the published config.

## Upgrading to v2.0.0

**Breaking upgrade:** publish and run the new migration, configure an account ID and every zone domain name, and grant Account → Email Sending → Edit before running suppression sync. Synchronous suppression API failures now warn and return success after event imports; monitor reported exceptions and warning output as well as exit codes.

```bash
composer update draliragab/filament-cloudflare-mail-monitor
php artisan vendor:publish --tag="cloudflare-mail-monitor-migrations"
php artisan migrate
```

Merge the new `account_id` key from the package configuration into your published config without overwriting existing customizations:

```php
'account_id' => env('CLOUDFLARE_MAIL_MONITOR_ACCOUNT_ID') ?: env('CLOUDFLARE_ACCOUNT_ID'),
```

Set either account environment variable and ensure each configured zone has its domain name. Rebuild cached configuration (`php artisan config:cache`) and restart queue workers (`php artisan queue:restart`). The additive migration preserves existing suppression rows and adds nullable `scope_type`, `scope_value`, and `read_only` columns.

Run `php artisan cloudflare-mail-monitor:fetch` and confirm both stored-count messages and suppression records in Filament. An exit code of zero alone does not confirm suppression sync succeeded. Check the next scheduled run before closing the incident.
