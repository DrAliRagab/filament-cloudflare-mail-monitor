<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor;

use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Pages\CloudflareMailDashboard;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailStatsOverview;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class CloudflareMailMonitorPlugin implements Plugin
{
    private bool $hasDashboard = true;

    private bool $hasLogsResource = true;

    private bool $hasStatsWidget = true;

    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'cloudflare-mail-monitor';
    }

    public function dashboard(bool $condition = true): self
    {
        $this->hasDashboard = $condition;

        return $this;
    }

    public function logsResource(bool $condition = true): self
    {
        $this->hasLogsResource = $condition;

        return $this;
    }

    public function statsWidget(bool $condition = true): self
    {
        $this->hasStatsWidget = $condition;

        return $this;
    }

    public function hasDashboard(): bool
    {
        return $this->hasDashboard;
    }

    public function hasLogsResource(): bool
    {
        return $this->hasLogsResource;
    }

    public function hasStatsWidget(): bool
    {
        return $this->hasStatsWidget;
    }

    public function register(Panel $panel): void
    {
        if ($this->hasDashboard) {
            $panel->pages([CloudflareMailDashboard::class]);
        }

        if ($this->hasLogsResource) {
            $panel->resources([CloudflareMailEventResource::class]);
        }

        if ($this->hasStatsWidget) {
            $panel->widgets([CloudflareMailStatsOverview::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
