<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Pages;

use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailAuthenticationOverview;
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

    public function hasConfiguredMailMonitor(): bool
    {
        return Config::string('api.token') !== null
            && Config::string('api.token') !== ''
            && Config::zones() !== [];
    }

    public function configurationWarning(): ?string
    {
        if (Config::string('api.token') === null || Config::string('api.token') === '') {
            return 'Set `CLOUDFLARE_MAIL_MONITOR_API_TOKEN` before using the dashboard.';
        }

        if (Config::zones() === []) {
            return 'Configure at least one zone in `CLOUDFLARE_MAIL_MONITOR_ZONE_ID` or the published config.';
        }

        return null;
    }

    public function authorizationWarning(): ?string
    {
        $lastRefreshError = session('cloudflare-mail-monitor.last_refresh_error');

        if (! is_array($lastRefreshError)) {
            return null;
        }

        if (($lastRefreshError['type'] ?? null) !== CloudflareApiException::class) {
            return null;
        }

        if (! in_array($lastRefreshError['status_code'] ?? null, [401, 403], true)) {
            return null;
        }

        return 'The Cloudflare API token is missing the required Analytics Read permission for at least one configured zone.';
    }

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
    public static function getNavigationIcon(): ?string
    {
        return Config::string('filament.dashboard_navigation_icon', 'heroicon-o-chart-bar');
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
        try {
            $stored = app(CloudflareMailEventFetcher::class)->fetch();

            session()->forget('cloudflare-mail-monitor.last_refresh_error');

            Notification::make()
                ->title(sprintf('Stored %d Cloudflare mail event(s).', $stored))
                ->success()
                ->send();
        } catch (CloudflareApiException $cloudflareApiException) {
            session()->put('cloudflare-mail-monitor.last_refresh_error', [
                'type' => $cloudflareApiException::class,
                'status_code' => $cloudflareApiException->statusCode(),
            ]);

            Notification::make()
                ->title('Cloudflare refresh failed.')
                ->body($this->authorizationWarning() ?? $cloudflareApiException->getMessage())
                ->danger()
                ->send();
        }
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
            CloudflareMailAuthenticationOverview::class,
        ];
    }
}
