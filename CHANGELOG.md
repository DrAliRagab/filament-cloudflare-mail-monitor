# Changelog

## v2.0.0 (2026-11-16)

**Breaking changes:** suppression sync requires an explicit account ID and a domain name for every configured zone, plus the new migration. Tokens need Account → Email Sending → Edit. Synchronous suppression API failures now return success after reporting an incomplete sync; queued failures retain retries.

- Replace the obsolete zone suppression endpoint with the account API and cursor pagination.
- Add account ID configuration with `CLOUDFLARE_ACCOUNT_ID` fallback; require explicit zone domain names.
- Associate account and sending-domain suppressions with configured zones and subdomains.
- Add nullable scope and read-only metadata through an additive migration and display it in Filament.
- Report synchronous suppression API failures without failing successful event imports; retain queued retries.

Upgrade: publish/run migrations, merge the new config key, set the account ID, refresh config caches, and restart workers. See README for details.

Incident references: MDSTER-LARAVEL-2Y, MDSTER-LARAVEL-2Z, ANESTHESIAZONE-M, ANESTHESIAZONE-N, ROASBY-5, ROASBY-6.
