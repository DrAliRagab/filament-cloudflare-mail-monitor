<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Pages;

use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailFailureOverview;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailStatsOverview;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailEventFetcher;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

final class CloudflareMailDashboard extends Page
{
    protected static ?string $slug = 'cloudflare-mail-monitor';

    protected string $view = 'filament-cloudflare-mail-monitor::pages.dashboard';

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return 'Mail Monitor';
    }

    #[\Override]
    public static function getNavigationGroup(): ?string
    {
        return Config::string('filament.navigation_group', 'Cloudflare');
    }

    #[\Override]
    public static function getNavigationSort(): int
    {
        return Config::integer('filament.navigation_sort', 90);
    }

    #[\Override]
    public static function shouldRegisterNavigation(): bool
    {
        return Config::boolean('filament.should_register_navigation', true);
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Cloudflare Mail Monitor';
    }

    /**
     * @return array<Action>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh logs')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshCloudflareMailEvents'),
        ];
    }

    public function refreshCloudflareMailEvents(): void
    {
        $stored = app(CloudflareMailEventFetcher::class)->fetch();

        Notification::make()
            ->title(sprintf('Stored %d Cloudflare mail event(s).', $stored))
            ->success()
            ->send();
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    #[\Override]
    protected function getHeaderWidgets(): array
    {
        return [
            CloudflareMailStatsOverview::class,
            CloudflareMailFailureOverview::class,
        ];
    }
}
