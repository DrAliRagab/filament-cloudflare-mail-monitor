<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Support;

use Illuminate\Support\Arr;

final class Config
{
    public static function string(string $key, ?string $default = null): ?string
    {
        $value = config("cloudflare-mail-monitor.{$key}", $default);

        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }

    public static function integer(string $key, int $default): int
    {
        return (int) config("cloudflare-mail-monitor.{$key}", $default);
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        return (bool) config("cloudflare-mail-monitor.{$key}", $default);
    }

    /**
     * @return array<int, array{id: string|null, name?: string|null}>
     */
    public static function zones(): array
    {
        $zones = config('cloudflare-mail-monitor.zones', []);

        if (! is_array($zones)) {
            return [];
        }

        return array_values(array_filter($zones, static function (mixed $zone): bool {
            return is_array($zone) && is_string(Arr::get($zone, 'id')) && Arr::get($zone, 'id') !== '';
        }));
    }
}
