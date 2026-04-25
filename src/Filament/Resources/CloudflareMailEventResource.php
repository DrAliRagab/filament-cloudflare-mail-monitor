<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources;

use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource\Pages\ListCloudflareMailEvents;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Privacy;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * @extends resource<CloudflareMailEvent>
 */
final class CloudflareMailEventResource extends Resource
{
    protected static ?string $model = CloudflareMailEvent::class;

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return 'Email Logs';
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return 'email log';
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return 'email logs';
    }

    #[\Override]
    public static function getNavigationGroup(): ?string
    {
        return Config::string('filament.navigation_group', 'Cloudflare');
    }

    #[\Override]
    public static function getNavigationIcon(): ?string
    {
        return Config::string('filament.navigation_icon', 'heroicon-o-envelope');
    }

    #[\Override]
    public static function getNavigationSort(): int
    {
        return Config::integer('filament.navigation_sort', 90) + 1;
    }

    #[\Override]
    public static function shouldRegisterNavigation(): bool
    {
        return Config::boolean('filament.should_register_navigation', true);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('zone_name')->label('Zone')->searchable()->toggleable(),
                TextColumn::make('status')->badge()->searchable()->sortable(),
                TextColumn::make('event_type')->label('Event')->badge()->toggleable(),
                TextColumn::make('from')->formatStateUsing(static fn (?string $state): ?string => Privacy::email($state))->searchable(),
                TextColumn::make('to')->formatStateUsing(static fn (?string $state): ?string => Privacy::email($state))->searchable(),
                TextColumn::make('subject')->formatStateUsing(static fn (?string $state): ?string => Privacy::subject($state))->searchable()->limit(50),
                TextColumn::make('message_id')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sending_domain')->searchable()->toggleable(),
                TextColumn::make('error_cause')->badge()->searchable()->toggleable(),
                IconColumn::make('is_spam')->boolean()->toggleable(),
                IconColumn::make('is_ndr')->label('NDR')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('zone_id')->label('Zone')->attribute('zone_id'),
                SelectFilter::make('status')->options([
                    'delivered' => 'Delivered',
                    'deliveryFailed' => 'Delivery failed',
                    'rejected' => 'Rejected',
                    'failed' => 'Failed',
                    'sent' => 'Sent',
                ]),
                SelectFilter::make('dkim')->options(['pass' => 'Pass', 'fail' => 'Fail']),
                SelectFilter::make('dmarc')->options(['pass' => 'Pass', 'fail' => 'Fail']),
                SelectFilter::make('spf')->options(['pass' => 'Pass', 'fail' => 'Fail']),
                TernaryFilter::make('is_spam'),
                TernaryFilter::make('is_ndr')->label('NDR'),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->striped();
    }

    /**
     * @return array<string, PageRegistration>
     */
    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListCloudflareMailEvents::route('/'),
        ];
    }
}
