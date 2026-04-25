<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Support;

use Illuminate\Support\Arr;
use Stringable;

final class Config
{
    public static function string(string $key, ?string $default = null): ?string
    {
        $value = config('cloudflare-mail-monitor.'.$key, $default);

        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return $default;
    }

    public static function integer(string $key, int $default): int
    {
        $value = config('cloudflare-mail-monitor.'.$key, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        $value = config('cloudflare-mail-monitor.'.$key, $default);

        return is_bool($value) ? $value : $default;
    }

    /**
     * @return list<array{id: string, name?: string|null}>
     */
    public static function zones(): array
    {
        $zones = config('cloudflare-mail-monitor.zones', []);

        if (! is_array($zones)) {
            return [];
        }

        $configuredZones = [];

        foreach ($zones as $zone) {
            if (! is_array($zone)) {
                continue;
            }

            $id = Arr::get($zone, 'id');
            if (! is_string($id)) {
                continue;
            }

            if ($id === '') {
                continue;
            }

            $name = Arr::get($zone, 'name');
            $configuredZones[] = [
                'id' => $id,
                'name' => is_string($name) && $name !== '' ? $name : null,
            ];
        }

        return $configuredZones;
    }
}
