# Implementation Plan

This document revises the original package prompt into the current build plan and tracks missing parts as the package evolves.

## Revised Goal

Build `draliragab/filament-cloudflare-mail-monitor`, a Laravel 12 and Filament 5 package that monitors outbound Cloudflare Email Service sending activity.

The package must:

- Fetch outbound Cloudflare Email Service analytics and event logs from Cloudflare.
- Store normalized email events in the Laravel database.
- Provide a Filament 5 plugin with dashboard, widgets, and searchable log views.
- Support daily scheduled fetching and manual dashboard refresh.
- Prune old records after 90 days by default, with configurable retention.
- Keep all user-facing behavior configurable through published config and plugin options.
- Use strong typing, DTOs, Eloquent models, jobs, commands, and service classes.
- Test every implementation path, including non-happy paths.
- Pass `composer check` before each step is considered complete.
- Commit each completed step with a descriptive message.

## Cloudflare Source Of Truth

Cloudflare Email Service is evolving, so implementation decisions should prefer current docs and API references over pre-trained assumptions.

Current outbound observability source:

- GraphQL Analytics API dataset `emailSendingAdaptive` for individual outbound email events.
- GraphQL Analytics API dataset `emailSendingAdaptiveGroups` for aggregated outbound sending metrics.
- These are zone-level datasets queried with `zoneTag`.
- The API token needs `Analytics Read` for configured zones.
- Cloudflare currently documents a 31-day analytics retention window.

Important current scope decision:

- Inbound Email Routing is explicitly out of scope for this package version.
- Email sending itself is out of scope; this package monitors outbound events only.

## Current Architecture

Package namespace:

```txt
DrAliRagab\FilamentCloudflareMailMonitor
```

Composer package:

```txt
draliragab/filament-cloudflare-mail-monitor
```

Primary layers:

- `CloudflareGraphqlClient` handles authenticated Cloudflare GraphQL requests.
- `CloudflareMailEventFetcher` fetches `emailSendingAdaptive` events and upserts them locally.
- `FetchCloudflareMailEvents` runs fetches through Laravel queue infrastructure.
- `cloudflare-mail-monitor:fetch` runs or dispatches fetches.
- `cloudflare-mail-monitor:prune` removes records older than configured retention.
- `CloudflareMailEvent` stores normalized outbound event data.
- DTOs keep API data and date ranges strongly typed.

## Completed Steps

- Initialized git repository and Laravel package skeleton.
- Added Composer metadata, Laravel package discovery, CI workflow, Pest, Pint, PHPStan, Rector, and strict `composer check` workflow.
- Added publishable config with API, zones, fetch, retention, privacy, and Filament settings.
- Added database migration for `cloudflare_mail_monitor_events`.
- Added strongly typed DTOs for configured zones, date ranges, and Cloudflare email events.
- Added `CloudflareMailEvent` Eloquent model and query scopes.
- Added Cloudflare GraphQL client with explicit malformed response, HTTP failure, GraphQL error, and missing-token handling.
- Added fetch service, queued job, fetch command, and prune command.
- Added Filament 5 plugin registration.
- Added dashboard page with manual refresh action.
- Added Email Logs resource backed by local `CloudflareMailEvent` records.
- Added stats widget for total, delivered, failed, and spam/NDR event counts.
- Added privacy formatting helpers for masked email addresses and hidden subjects.
- Added focused tests for success paths, validation failures, malformed Cloudflare data, token errors, GraphQL errors, queue dispatch, synchronous fetches, pruning, and defensive DTO normalization.
- Added focused tests for Filament plugin registration, configurable navigation, dashboard refresh, logs table configuration, privacy formatting, summary service, and widget stats.
- Verified the current implementation with `composer check` and 100% reported coverage.

## Remaining Main Plan

1. Add aggregate query support for `emailSendingAdaptiveGroups`.
2. Add delivery failure analytics page/widget.
3. Add authentication health page/widget.
4. Add date range filtering to the Filament logs resource.
5. Add zone filter options from configured zones.
6. Revise README with final screenshots placeholders and troubleshooting.
7. Run `composer check` and commit after each completed step.

## Missing-Part Checklist

The following items are not complete yet and must be checked before a release:

- Delivery failure analytics page/widget is not implemented yet.
- Authentication health page/widget is not implemented yet.
- Date range filter is not implemented yet.
- Configured zone filter options are not implemented yet.
- `emailSendingAdaptiveGroups` aggregate query support is not implemented yet.
- README still needs final screenshots placeholders and troubleshooting.
- CI currently runs `composer check`, but this must be revalidated after Filament UI implementation.

## Quality Gate

Every implementation step must pass:

```bash
composer check
```

This runs:

- Rector
- Pint
- PHPStan at max level with strict type coverage rules
- Pest with 99% minimum coverage

If Rector or Pint changes files, run `composer check` again until the command is clean and idempotent.
