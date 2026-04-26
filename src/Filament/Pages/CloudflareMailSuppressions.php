<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Pages;

use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailSuppressionData;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSuppressionFetcher;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Privacy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class CloudflareMailSuppressions extends Page
{
    protected static ?string $slug = 'cloudflare-mail-suppressions';

    protected string $view = 'filament-cloudflare-mail-monitor::pages.suppressions';

    /**
     * @var list<array<string, mixed>>
     */
    public array $suppressions = [];

    public function mount(): void
    {
        if ($this->configurationWarning() !== null) {
            return;
        }

        $this->loadCloudflareMailSuppressions(sendNotification: false);
    }

    public function hasConfiguredMailSuppressions(): bool
    {
        return Config::string('api.token') !== null
            && Config::string('api.token') !== ''
            && Config::zones() !== [];
    }

    public function configurationWarning(): ?string
    {
        if (Config::string('api.token') === null || Config::string('api.token') === '') {
            return 'Set `CLOUDFLARE_MAIL_MONITOR_API_TOKEN` before using the suppression list.';
        }

        if (Config::zones() === []) {
            return 'Configure at least one zone in `CLOUDFLARE_MAIL_MONITOR_ZONE_ID` or the published config.';
        }

        return null;
    }

    public function authorizationWarning(): ?string
    {
        $lastSuppressionError = session('cloudflare-mail-monitor.last_suppression_error');

        if (! is_array($lastSuppressionError)) {
            return null;
        }

        if (($lastSuppressionError['type'] ?? null) !== CloudflareApiException::class) {
            return null;
        }

        if (! in_array($lastSuppressionError['status_code'] ?? null, [401, 403], true)) {
            return null;
        }

        return 'The Cloudflare API token is missing permission to read Email Sending suppressions for at least one configured zone.';
    }

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return 'Suppressions';
    }

    #[\Override]
    public static function getNavigationGroup(): ?string
    {
        return Config::string('filament.navigation_group', 'Cloudflare');
    }

    #[\Override]
    public static function getNavigationIcon(): ?string
    {
        return Config::string('filament.suppressions_navigation_icon', 'heroicon-o-no-symbol');
    }

    #[\Override]
    public static function getNavigationSort(): int
    {
        return Config::integer('filament.navigation_sort', 90) + 2;
    }

    #[\Override]
    public static function shouldRegisterNavigation(): bool
    {
        return Config::boolean('filament.should_register_navigation', true);
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Cloudflare Mail Suppressions';
    }

    /**
     * @return array<Action>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh suppressions')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshCloudflareMailSuppressions'),
        ];
    }

    public function refreshCloudflareMailSuppressions(): void
    {
        $this->loadCloudflareMailSuppressions();
    }

    public function loadCloudflareMailSuppressions(bool $sendNotification = true): void
    {
        try {
            $this->suppressions = array_map(
                static fn (EmailSuppressionData $emailSuppressionData): array => $emailSuppressionData->toArray(),
                app(CloudflareMailSuppressionFetcher::class)->fetch(),
            );

            session()->forget('cloudflare-mail-monitor.last_suppression_error');

            if ($sendNotification) {
                Notification::make()
                    ->title(sprintf('Loaded %d Cloudflare suppression(s).', count($this->suppressions)))
                    ->success()
                    ->send();
            }
        } catch (CloudflareApiException $cloudflareApiException) {
            session()->put('cloudflare-mail-monitor.last_suppression_error', [
                'type' => $cloudflareApiException::class,
                'status_code' => $cloudflareApiException->statusCode(),
            ]);

            if ($sendNotification) {
                Notification::make()
                    ->title('Cloudflare suppressions refresh failed.')
                    ->body($this->authorizationWarning() ?? $cloudflareApiException->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suppressionRows(): array
    {
        return array_map(static function (array $suppression): array {
            $zones = $suppression['zones'] ?? [];

            if (! is_array($zones)) {
                $zones = [];
            }

            return [
                ...$suppression,
                'email' => Privacy::email(is_string($suppression['email'] ?? null) ? $suppression['email'] : null),
                'zones_display' => implode(', ', array_filter($zones, is_scalar(...))),
            ];
        }, $this->suppressions);
    }
}
