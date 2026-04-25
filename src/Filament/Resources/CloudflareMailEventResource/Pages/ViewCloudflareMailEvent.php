<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource\Pages;

use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailEventFetcher;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewCloudflareMailEvent extends ViewRecord
{
    protected static string $resource = CloudflareMailEventResource::class;

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
        app(CloudflareMailEventFetcher::class)->fetch();

        $this->record = $this->getRecord()->refresh();
    }
}
