<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\CloudflareMailMonitorPlugin;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Pages\CloudflareMailDashboard;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailStatsOverview;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSummary;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Privacy;
use Filament\Panel;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
});

it('registers Filament pages resources and widgets by default', function (): void {
    $panel = Panel::make()->id('admin');

    CloudflareMailMonitorPlugin::make()->register($panel);

    expect($panel->getPages())->toContain(CloudflareMailDashboard::class)
        ->and($panel->getResources())->toContain(CloudflareMailEventResource::class)
        ->and($panel->getWidgets())->toContain(CloudflareMailStatsOverview::class);
});

it('can disable Filament plugin parts fluently', function (): void {
    $panel = Panel::make()->id('admin');
    $cloudflareMailMonitorPlugin = CloudflareMailMonitorPlugin::make()
        ->dashboard(false)
        ->logsResource(false)
        ->statsWidget(false);

    $cloudflareMailMonitorPlugin->register($panel);

    expect($cloudflareMailMonitorPlugin->getId())->toBe('cloudflare-mail-monitor')
        ->and($cloudflareMailMonitorPlugin->hasDashboard())->toBeFalse()
        ->and($cloudflareMailMonitorPlugin->hasLogsResource())->toBeFalse()
        ->and($cloudflareMailMonitorPlugin->hasStatsWidget())->toBeFalse()
        ->and($panel->getPages())->not->toContain(CloudflareMailDashboard::class)
        ->and($panel->getResources())->not->toContain(CloudflareMailEventResource::class)
        ->and($panel->getWidgets())->not->toContain(CloudflareMailStatsOverview::class);
});

it('uses configurable Filament navigation values', function (): void {
    config()->set('cloudflare-mail-monitor.filament.navigation_group', 'Ops');
    config()->set('cloudflare-mail-monitor.filament.navigation_icon', 'heroicon-o-chart-bar');
    config()->set('cloudflare-mail-monitor.filament.navigation_sort', 10);
    config()->set('cloudflare-mail-monitor.filament.should_register_navigation', false);

    expect(CloudflareMailDashboard::getNavigationLabel())->toBe('Mail Monitor')
        ->and(CloudflareMailDashboard::getNavigationGroup())->toBe('Ops')
        ->and(CloudflareMailDashboard::getNavigationSort())->toBe(10)
        ->and(CloudflareMailDashboard::shouldRegisterNavigation())->toBeFalse()
        ->and(CloudflareMailEventResource::getNavigationLabel())->toBe('Email Logs')
        ->and(CloudflareMailEventResource::getModelLabel())->toBe('email log')
        ->and(CloudflareMailEventResource::getPluralModelLabel())->toBe('email logs')
        ->and(CloudflareMailEventResource::getNavigationGroup())->toBe('Ops')
        ->and(CloudflareMailEventResource::getNavigationIcon())->toBe('heroicon-o-chart-bar')
        ->and(CloudflareMailEventResource::getNavigationSort())->toBe(11)
        ->and(CloudflareMailEventResource::shouldRegisterNavigation())->toBeFalse();
});

it('configures the email logs table and pages', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
        ['id' => 'zone-2'],
    ]);

    $table = Table::make(Mockery::mock(HasTable::class));
    $configuredTable = CloudflareMailEventResource::table($table);

    expect($configuredTable)->toBeInstanceOf(Table::class)
        ->and(CloudflareMailEventResource::getPages())->toHaveKey('index')
        ->and($configuredTable->getFilters())->toHaveKeys(['zone_id', 'occurred_at'])
        ->and(CloudflareMailEventResource::zoneFilterOptions())->toBe([
            'zone-1' => 'example.com',
            'zone-2' => 'zone-2',
        ]);
});

it('filters email logs by occurred date ranges', function (): void {
    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('a', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-20T10:00:00Z'),
    ]);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('b', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
    ]);

    $table = CloudflareMailEventResource::table(Table::make(Mockery::mock(HasTable::class)));
    $dateFilter = $table->getFilter('occurred_at');

    if (! $dateFilter instanceof BaseFilter) {
        throw new RuntimeException('The occurred_at filter was not registered.');
    }

    $eventHashes = $dateFilter
        ->apply(CloudflareMailEvent::query(), ['from' => '2026-04-21', 'until' => '2026-04-30'])
        ->pluck('event_hash')
        ->all();

    expect($eventHashes)->toBe([str_repeat('b', 64)]);
});

it('formats private email data according to config', function (): void {
    config()->set('cloudflare-mail-monitor.privacy.mask_email_addresses', true);
    config()->set('cloudflare-mail-monitor.privacy.show_subjects', false);

    expect(Privacy::email('person@example.com'))->toBe('p***@example.com')
        ->and(Privacy::email('invalid'))->toBe('***')
        ->and(Privacy::email(null))->toBeNull()
        ->and(Privacy::subject('Secret'))->toBe('[hidden]')
        ->and(Privacy::subject(null))->toBeNull();
});

it('summarizes stored mail events for widgets', function (): void {
    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('a', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
        'status' => 'delivered',
    ]);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('b', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:01:00Z'),
        'status' => 'deliveryFailed',
        'is_ndr' => true,
    ]);

    expect(app(CloudflareMailSummary::class)->totals())->toBe([
        'total' => 2,
        'delivered' => 1,
        'failed' => 1,
        'spam_or_ndr' => 1,
    ]);
});

it('builds dashboard widgets and stats', function (): void {
    $cloudflareMailDashboard = app(CloudflareMailDashboard::class);
    $cloudflareMailStatsOverview = app(CloudflareMailStatsOverview::class);

    $actions = Closure::bind(static fn (): array => $cloudflareMailDashboard->getHeaderActions(), null, CloudflareMailDashboard::class)();
    $widgets = Closure::bind(static fn (): array => $cloudflareMailDashboard->getHeaderWidgets(), null, CloudflareMailDashboard::class)();
    $stats = Closure::bind(static fn (): array => $cloudflareMailStatsOverview->getStats(), null, CloudflareMailStatsOverview::class)();

    expect($cloudflareMailDashboard->getTitle())->toBe('Cloudflare Mail Monitor')
        ->and($actions)->toHaveCount(1)
        ->and($widgets)->toBe([CloudflareMailStatsOverview::class])
        ->and($stats)->toHaveCount(4);
});

it('refreshes Cloudflare mail events from the dashboard action target', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => [[
                            'datetime' => '2026-04-25T10:15:00Z',
                            'messageId' => 'dashboard-refresh',
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    app(CloudflareMailDashboard::class)->refreshCloudflareMailEvents();

    expect(CloudflareMailEvent::query()->where('message_id', 'dashboard-refresh')->exists())->toBeTrue();
});
