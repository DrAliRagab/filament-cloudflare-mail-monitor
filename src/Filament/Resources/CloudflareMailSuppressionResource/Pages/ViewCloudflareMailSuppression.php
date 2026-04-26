<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailSuppressionResource\Pages;

use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailSuppressionResource;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSuppressionFetcher;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewCloudflareMailSuppression extends ViewRecord
{
    protected static string $resource = CloudflareMailSuppressionResource::class;

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
        app(CloudflareMailSuppressionFetcher::class)->fetch();

        $this->record = $this->getRecord()->refresh();
    }
}
