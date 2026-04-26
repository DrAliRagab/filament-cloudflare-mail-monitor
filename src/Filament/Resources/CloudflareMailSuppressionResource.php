<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources;

use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailSuppressionResource\Pages\ListCloudflareMailSuppressions;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailSuppressionResource\Pages\ViewCloudflareMailSuppression;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Privacy;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends resource<CloudflareMailSuppression>
 */
final class CloudflareMailSuppressionResource extends Resource
{
    protected static ?string $model = CloudflareMailSuppression::class;

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return 'Suppressions';
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return 'suppression';
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return 'suppressions';
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
    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(static fn (Model $model): string => self::getUrl('view', ['record' => $model]))
            ->columns([
                TextColumn::make('suppressed_at')->dateTime()->sortable(),
                TextColumn::make('zone_name')->label('Zone')->searchable()->toggleable(),
                TextColumn::make('email')->formatStateUsing(static fn (?string $state): ?string => Privacy::email($state))->searchable()->sortable(),
                TextColumn::make('reason')->badge()->searchable()->sortable(),
                TextColumn::make('expires_at')->dateTime()->placeholder('Never')->sortable(),
                TextColumn::make('suppression_id')->label('Suppression ID')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('zone_id')->label('Zone')->attribute('zone_id')->options(self::zoneFilterOptions()),
                SelectFilter::make('reason')->options(fn (): array => self::reasonFilterOptions()),
                Filter::make('suppressed_at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (mixed $query, array $data): void {
                        if ($query instanceof Builder) {
                            self::applySuppressedAtFilter($query, $data);
                        }
                    }),
            ])
            ->defaultSort('suppressed_at', 'desc')
            ->striped();
    }

    /**
     * @return array<string, PageRegistration>
     */
    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListCloudflareMailSuppressions::route('/'),
            'view' => ViewCloudflareMailSuppression::route('/{record}'),
        ];
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('suppressed_at')->dateTime()->label('Suppressed at'),
            TextEntry::make('expires_at')->dateTime()->placeholder('Never'),
            TextEntry::make('zone_name')->label('Zone')->placeholder('Unknown zone'),
            TextEntry::make('zone_id')->label('Zone ID'),
            TextEntry::make('email')->formatStateUsing(static fn (?string $state): ?string => Privacy::email($state)),
            TextEntry::make('reason')->badge(),
            TextEntry::make('suppression_id')->label('Suppression ID'),
            TextEntry::make('cloudflare_zones')
                ->label('Cloudflare zones')
                ->formatStateUsing(static fn (mixed $state): ?string => self::formatStringList($state))
                ->placeholder('None'),
            TextEntry::make('raw')
                ->label('Raw payload')
                ->formatStateUsing(static fn (mixed $state): ?string => self::formatRawPayload($state))
                ->placeholder('Not available')
                ->columnSpanFull(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function zoneFilterOptions(): array
    {
        $options = [];

        foreach (Config::zones() as $zone) {
            $options[$zone['id']] = $zone['name'] ?? $zone['id'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function reasonFilterOptions(): array
    {
        /** @var list<string> $reasons */
        $reasons = CloudflareMailSuppression::query()
            ->whereNotNull('reason')
            ->distinct()
            ->orderBy('reason')
            ->pluck('reason')
            ->all();

        return array_combine($reasons, array_map(static fn (string $reason): string => str($reason)->headline()->toString(), $reasons));
    }

    /**
     * @param  Builder<CloudflareMailSuppression>  $builder
     * @param  array<array-key, mixed>  $data
     * @return Builder<CloudflareMailSuppression>
     */
    public static function applySuppressedAtFilter(Builder $builder, array $data): Builder
    {
        $from = $data['from'] ?? null;
        $until = $data['until'] ?? null;

        if (is_string($from) && $from !== '') {
            $builder->whereDate('suppressed_at', '>=', $from);
        }

        if (is_string($until) && $until !== '') {
            $builder->whereDate('suppressed_at', '<=', $until);
        }

        return $builder;
    }

    public static function formatStringList(mixed $state): ?string
    {
        if (! is_array($state)) {
            return null;
        }

        $values = array_filter($state, is_scalar(...));

        return $values === [] ? null : implode(', ', $values);
    }

    public static function formatRawPayload(mixed $state): ?string
    {
        if ($state === null) {
            return null;
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $state = $decoded;
            }
        }

        if (! is_array($state)) {
            if (is_scalar($state)) {
                return var_export($state, true);
            }

            if ($state instanceof \Stringable) {
                return (string) $state;
            }

            return null;
        }

        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }
}
