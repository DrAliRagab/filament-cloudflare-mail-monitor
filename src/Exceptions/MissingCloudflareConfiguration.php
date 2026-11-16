<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Exceptions;

final class MissingCloudflareConfiguration extends CloudflareMailMonitorException
{
    public static function accountId(): self
    {
        return new self('Cloudflare account ID is not configured. Set CLOUDFLARE_MAIL_MONITOR_ACCOUNT_ID or CLOUDFLARE_ACCOUNT_ID.');
    }

    public static function zoneName(string $zoneId): self
    {
        return new self(sprintf('Cloudflare zone %s requires a domain name for suppression sync. Configure zones[].name.', $zoneId));
    }

    public static function apiToken(): self
    {
        return new self('Cloudflare API token is not configured. Set CLOUDFLARE_MAIL_MONITOR_API_TOKEN.');
    }
}
