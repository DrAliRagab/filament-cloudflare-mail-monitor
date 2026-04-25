<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Exceptions;

final class MissingCloudflareConfiguration extends CloudflareMailMonitorException
{
    public static function apiToken(): self
    {
        return new self('Cloudflare API token is not configured. Set CLOUDFLARE_MAIL_MONITOR_API_TOKEN.');
    }
}
