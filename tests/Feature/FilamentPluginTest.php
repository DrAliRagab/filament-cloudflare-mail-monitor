<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\CloudflareMailMonitorPlugin;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Pages\CloudflareMailDashboard;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource\Pages\ListCloudflareMailEvents;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource\Pages\ViewCloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailSuppressionResource;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailSuppressionResource\Pages\ListCloudflareMailSuppressions;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailSuppressionResource\Pages\ViewCloudflareMailSuppression;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailAuthenticationOverview;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailFailureOverview;
use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets\CloudflareMailStatsOverview;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailEventFetcher;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSummary;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Privacy;
use Filament\Panel;
use Filament\Schemas\Schema;
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
        ->and($panel->getResources())->toContain(CloudflareMailSuppressionResource::class)
        ->and($panel->getWidgets())->toContain(CloudflareMailStatsOverview::class)
        ->and($panel->getWidgets())->toContain(CloudflareMailFailureOverview::class)
        ->and($panel->getWidgets())->toContain(CloudflareMailAuthenticationOverview::class);
});

it('can disable Filament plugin parts fluently', function (): void {
    $panel = Panel::make()->id('admin');
    $cloudflareMailMonitorPlugin = CloudflareMailMonitorPlugin::make()
        ->dashboard(false)
        ->suppressionsResource(false)
        ->logsResource(false)
        ->statsWidget(false);

    $cloudflareMailMonitorPlugin->register($panel);

    expect($cloudflareMailMonitorPlugin->getId())->toBe('cloudflare-mail-monitor')
        ->and($cloudflareMailMonitorPlugin->hasDashboard())->toBeFalse()
        ->and($cloudflareMailMonitorPlugin->hasSuppressionsResource())->toBeFalse()
        ->and($cloudflareMailMonitorPlugin->hasLogsResource())->toBeFalse()
        ->and($cloudflareMailMonitorPlugin->hasStatsWidget())->toBeFalse()
        ->and($panel->getPages())->not->toContain(CloudflareMailDashboard::class)
        ->and($panel->getResources())->not->toContain(CloudflareMailEventResource::class)
        ->and($panel->getResources())->not->toContain(CloudflareMailSuppressionResource::class)
        ->and($panel->getWidgets())->not->toContain(CloudflareMailStatsOverview::class)
        ->and($panel->getWidgets())->not->toContain(CloudflareMailFailureOverview::class)
        ->and($panel->getWidgets())->not->toContain(CloudflareMailAuthenticationOverview::class);
});

it('uses configurable Filament navigation values', function (): void {
    config()->set('cloudflare-mail-monitor.filament.navigation_group', 'Ops');
    config()->set('cloudflare-mail-monitor.filament.dashboard_navigation_icon', 'heroicon-o-chart-pie');
    config()->set('cloudflare-mail-monitor.filament.navigation_icon', 'heroicon-o-chart-bar');
    config()->set('cloudflare-mail-monitor.filament.suppressions_navigation_icon', 'heroicon-o-no-symbol');
    config()->set('cloudflare-mail-monitor.filament.navigation_sort', 10);
    config()->set('cloudflare-mail-monitor.filament.should_register_navigation', false);

    expect(CloudflareMailDashboard::getNavigationLabel())->toBe('Mail Monitor')
        ->and(CloudflareMailDashboard::getNavigationGroup())->toBe('Ops')
        ->and(CloudflareMailDashboard::getNavigationIcon())->toBe('heroicon-o-chart-pie')
        ->and(CloudflareMailDashboard::getNavigationSort())->toBe(10)
        ->and(CloudflareMailDashboard::shouldRegisterNavigation())->toBeFalse()
        ->and(CloudflareMailSuppressionResource::getNavigationLabel())->toBe('Suppressions')
        ->and(CloudflareMailSuppressionResource::getModelLabel())->toBe('suppression')
        ->and(CloudflareMailSuppressionResource::getPluralModelLabel())->toBe('suppressions')
        ->and(CloudflareMailSuppressionResource::getNavigationGroup())->toBe('Ops')
        ->and(CloudflareMailSuppressionResource::getNavigationIcon())->toBe('heroicon-o-no-symbol')
        ->and(CloudflareMailSuppressionResource::getNavigationSort())->toBe(12)
        ->and(CloudflareMailSuppressionResource::shouldRegisterNavigation())->toBeFalse()
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
        ->and(CloudflareMailEventResource::getPages())->toHaveKey('view')
        ->and(CloudflareMailEventResource::getPages()['view']->getPage())->toBe(ViewCloudflareMailEvent::class)
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

it('links email logs to the detail page', function (): void {
    $table = CloudflareMailEventResource::table(Table::make(Mockery::mock(HasTable::class)));

    expect($table->hasCustomRecordUrl())->toBeTrue();
});

it('exposes a refresh action on the logs list page', function (): void {
    $actions = Closure::bind(static fn (): array => app(ListCloudflareMailEvents::class)->getHeaderActions(), null, ListCloudflareMailEvents::class)();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->getName())->toBe('refresh')
        ->and($actions[0]->getLabel())->toBe('Refresh logs');
});

it('refreshes the logs list page cache after fetching', function (): void {
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
                            'messageId' => 'list-refresh',
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    $listCloudflareMailEvents = app(ListCloudflareMailEvents::class);
    $listCloudflareMailEvents->refreshCloudflareMailEvents();

    expect(CloudflareMailEvent::query()->where('message_id', 'list-refresh')->exists())->toBeTrue();
});

it('exposes a refresh action on the log detail page', function (): void {
    $actions = Closure::bind(static fn (): array => app(ViewCloudflareMailEvent::class)->getHeaderActions(), null, ViewCloudflareMailEvent::class)();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->getName())->toBe('refresh')
        ->and($actions[0]->getLabel())->toBe('Refresh logs');
});

it('refreshes the current log record after fetching', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    $cloudflareMailEvent = CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('f', 64),
        'zone_id' => 'zone-1',
        'zone_name' => 'example.com',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:15:00Z'),
        'message_id' => 'detail-refresh',
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptive' => [[
                            'datetime' => '2026-04-25T10:15:00Z',
                            'messageId' => 'detail-refresh',
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    $viewCloudflareMailEvent = app(ViewCloudflareMailEvent::class);
    $viewCloudflareMailEvent->record = $cloudflareMailEvent;
    $viewCloudflareMailEvent->refreshCloudflareMailEvents();

    expect(CloudflareMailEvent::query()->where('message_id', 'detail-refresh')->exists())->toBeTrue();
});

it('builds the email log infolist', function (): void {
    $schema = CloudflareMailEventResource::infolist(Schema::make());
    $components = $schema->getComponents();

    $rawEntry = collect($components)->first(static fn ($component): bool => $component->getName() === 'raw');

    expect($components)->not->toBeEmpty()
        ->and($rawEntry)->not->toBeNull()
        ->and($rawEntry->formatState(['foo' => 'bar']))->toBe("{\n    \"foo\": \"bar\"\n}")
        ->and($rawEntry->formatState('{"foo":"bar"}'))->toBe("{\n    \"foo\": \"bar\"\n}")
        ->and($rawEntry->formatState(7))->toBe('7')
        ->and($rawEntry->formatState(new class implements Stringable
        {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        }))->toBe('stringable-value')
        ->and($rawEntry->formatState(new stdClass))->toBeNull()
        ->and($rawEntry->formatState(null))->toBeNull();
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
        'dkim' => 'pass',
        'dmarc' => 'pass',
        'spf' => 'pass',
    ]);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('b', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:01:00Z'),
        'status' => 'deliveryFailed',
        'is_ndr' => true,
        'dkim' => 'fail',
        'dmarc' => 'pass',
        'spf' => 'fail',
    ]);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('c', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:02:00Z'),
        'status' => 'rejected',
        'error_cause' => 'policy',
        'dkim' => 'pass',
        'dmarc' => 'fail',
        'spf' => 'pass',
    ]);

    CloudflareMailEvent::query()->create([
        'event_hash' => str_repeat('d', 64),
        'zone_id' => 'zone-1',
        'occurred_at' => CarbonImmutable::parse('2026-04-25T10:03:00Z'),
        'status' => 'failed',
        'error_cause' => 'policy',
        'dkim' => 'fail',
        'dmarc' => 'fail',
        'spf' => 'fail',
    ]);

    expect(app(CloudflareMailSummary::class)->totals())->toBe([
        'total' => 4,
        'delivered' => 1,
        'failed' => 3,
        'spam_or_ndr' => 1,
    ])->and(app(CloudflareMailSummary::class)->deliveryFailures())->toBe([
        'failed' => 3,
        'rejected' => 1,
        'ndr' => 1,
        'top_error_cause' => 'policy',
        'top_error_cause_count' => 2,
    ])->and(app(CloudflareMailSummary::class)->authenticationHealth())->toBe([
        'dkim_fail' => 2,
        'dmarc_fail' => 2,
        'spf_fail' => 2,
        'all_pass' => 1,
    ]);
});

it('builds dashboard widgets and stats', function (): void {
    $cloudflareMailDashboard = app(CloudflareMailDashboard::class);
    $cloudflareMailStatsOverview = app(CloudflareMailStatsOverview::class);
    $cloudflareMailFailureOverview = app(CloudflareMailFailureOverview::class);
    $cloudflareMailAuthenticationOverview = app(CloudflareMailAuthenticationOverview::class);

    $actions = Closure::bind(static fn (): array => $cloudflareMailDashboard->getHeaderActions(), null, CloudflareMailDashboard::class)();
    $widgets = Closure::bind(static fn (): array => $cloudflareMailDashboard->getHeaderWidgets(), null, CloudflareMailDashboard::class)();
    $stats = Closure::bind(static fn (): array => $cloudflareMailStatsOverview->getStats(), null, CloudflareMailStatsOverview::class)();
    $failureStats = Closure::bind(static fn (): array => $cloudflareMailFailureOverview->getStats(), null, CloudflareMailFailureOverview::class)();
    $authenticationStats = Closure::bind(static fn (): array => $cloudflareMailAuthenticationOverview->getStats(), null, CloudflareMailAuthenticationOverview::class)();

    expect($cloudflareMailDashboard->getTitle())->toBe('Cloudflare Mail Monitor')
        ->and($actions)->toHaveCount(1)
        ->and($widgets)->toBe([
            CloudflareMailStatsOverview::class,
            CloudflareMailFailureOverview::class,
            CloudflareMailAuthenticationOverview::class,
        ])
        ->and($stats)->toHaveCount(4)
        ->and($failureStats)->toHaveCount(4)
        ->and($authenticationStats)->toHaveCount(4);
});

it('shows a warning when mail monitor configuration is missing', function (): void {
    config()->set('cloudflare-mail-monitor.api.token');
    config()->set('cloudflare-mail-monitor.zones', []);

    $cloudflareMailDashboard = app(CloudflareMailDashboard::class);

    expect($cloudflareMailDashboard->hasConfiguredMailMonitor())->toBeFalse()
        ->and($cloudflareMailDashboard->configurationWarning())->toBe('Set `CLOUDFLARE_MAIL_MONITOR_API_TOKEN` before using the dashboard.');
});

it('does not warn when configuration is present', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    $cloudflareMailDashboard = app(CloudflareMailDashboard::class);

    expect($cloudflareMailDashboard->hasConfiguredMailMonitor())->toBeTrue()
        ->and($cloudflareMailDashboard->configurationWarning())->toBeNull();
});

it('warns when zones are missing', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.zones', []);

    $cloudflareMailDashboard = app(CloudflareMailDashboard::class);

    expect($cloudflareMailDashboard->hasConfiguredMailMonitor())->toBeFalse()
        ->and($cloudflareMailDashboard->configurationWarning())->toBe('Configure at least one zone in `CLOUDFLARE_MAIL_MONITOR_ZONE_ID` or the published config.');
});

it('shows a permissions warning after an authorization failure', function (): void {
    session()->put('cloudflare-mail-monitor.last_refresh_error', [
        'type' => CloudflareApiException::class,
        'status_code' => 403,
    ]);

    $cloudflareMailDashboard = app(CloudflareMailDashboard::class);

    expect($cloudflareMailDashboard->authorizationWarning())->toBe('The Cloudflare API token is missing the required Analytics Read permission for at least one configured zone.');
});

it('does not show a permissions warning without an authorization failure', function (): void {
    session()->put('cloudflare-mail-monitor.last_refresh_error', [
        'type' => 'other',
        'status_code' => 500,
    ]);

    expect(app(CloudflareMailDashboard::class)->authorizationWarning())->toBeNull();
});

it('does not show a permissions warning for non-auth Cloudflare errors', function (): void {
    session()->put('cloudflare-mail-monitor.last_refresh_error', [
        'type' => CloudflareApiException::class,
        'status_code' => 500,
    ]);

    expect(app(CloudflareMailDashboard::class)->authorizationWarning())->toBeNull();
});

it('does not show a permissions warning when no refresh error is stored', function (): void {
    session()->forget('cloudflare-mail-monitor.last_refresh_error');

    expect(app(CloudflareMailDashboard::class)->authorizationWarning())->toBeNull();
});

it('stores a permissions warning after a failed refresh', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    app()->instance(CloudflareMailEventFetcher::class, new class
    {
        public function fetch(): int
        {
            throw new CloudflareApiException('Cloudflare API request failed with HTTP status 403.', 403);
        }
    });

    app(CloudflareMailDashboard::class)->refreshCloudflareMailEvents();

    expect(session('cloudflare-mail-monitor.last_refresh_error'))->toBe([
        'type' => CloudflareApiException::class,
        'status_code' => 403,
    ])->and(app(CloudflareMailDashboard::class)->authorizationWarning())->toBe('The Cloudflare API token is missing the required Analytics Read permission for at least one configured zone.');
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

it('configures the email suppressions table and pages', function (): void {
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
        ['id' => 'zone-2'],
    ]);

    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'suppression-1',
        'zone_id' => 'zone-1',
        'zone_name' => 'example.com',
        'email' => 'person@example.com',
        'reason' => 'hard_bounce',
        'suppressed_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
        'cloudflare_zones' => ['example.com'],
        'raw' => ['id' => 'suppression-1'],
    ]);

    $table = Table::make(Mockery::mock(HasTable::class));
    $configuredTable = CloudflareMailSuppressionResource::table($table);

    expect($configuredTable)->toBeInstanceOf(Table::class)
        ->and(CloudflareMailSuppressionResource::getPages())->toHaveKey('index')
        ->and(CloudflareMailSuppressionResource::getPages())->toHaveKey('view')
        ->and(CloudflareMailSuppressionResource::getPages()['view']->getPage())->toBe(ViewCloudflareMailSuppression::class)
        ->and($configuredTable->getFilters())->toHaveKeys(['zone_id', 'reason', 'suppressed_at'])
        ->and(CloudflareMailSuppressionResource::zoneFilterOptions())->toBe([
            'zone-1' => 'example.com',
            'zone-2' => 'zone-2',
        ])->and(CloudflareMailSuppressionResource::reasonFilterOptions())->toBe([
            'hard_bounce' => 'Hard Bounce',
        ]);
});

it('filters email suppressions by suppressed date ranges', function (): void {
    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'suppression-1',
        'zone_id' => 'zone-1',
        'email' => 'one@example.com',
        'reason' => 'hard_bounce',
        'suppressed_at' => CarbonImmutable::parse('2026-04-20T10:00:00Z'),
    ]);

    CloudflareMailSuppression::query()->create([
        'suppression_id' => 'suppression-2',
        'zone_id' => 'zone-1',
        'email' => 'two@example.com',
        'reason' => 'manual',
        'suppressed_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
    ]);

    $table = CloudflareMailSuppressionResource::table(Table::make(Mockery::mock(HasTable::class)));
    $dateFilter = $table->getFilter('suppressed_at');

    if (! $dateFilter instanceof BaseFilter) {
        throw new RuntimeException('The suppressed_at filter was not registered.');
    }

    $suppressionIds = $dateFilter
        ->apply(CloudflareMailSuppression::query(), ['from' => '2026-04-21', 'until' => '2026-04-30'])
        ->pluck('suppression_id')
        ->all();

    expect($suppressionIds)->toBe(['suppression-2']);
});

it('links email suppressions to the detail page', function (): void {
    $table = CloudflareMailSuppressionResource::table(Table::make(Mockery::mock(HasTable::class)));

    expect($table->hasCustomRecordUrl())->toBeTrue();
});

it('exposes a refresh action on the suppressions list page', function (): void {
    $actions = Closure::bind(static fn (): array => app(ListCloudflareMailSuppressions::class)->getHeaderActions(), null, ListCloudflareMailSuppressions::class)();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->getName())->toBe('refresh')
        ->and($actions[0]->getLabel())->toBe('Refresh suppressions');
});

it('refreshes the suppressions list page cache after fetching', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 100,
            'total' => 1,
            'result' => [[
                'id' => 'suppression-1',
                'email' => 'person@example.com',
                'reason' => 'hard_bounce',
                'created_at' => '2026-04-25T10:00:00Z',
                'expires_at' => null,
                'zones' => ['example.com'],
            ]],
        ]),
    ]);

    $listCloudflareMailSuppressions = app(ListCloudflareMailSuppressions::class);
    $listCloudflareMailSuppressions->refreshCloudflareMailSuppressions();

    expect(CloudflareMailSuppression::query()->where('suppression_id', 'suppression-1')->exists())->toBeTrue();
});

it('exposes a refresh action on the suppression detail page', function (): void {
    $actions = Closure::bind(static fn (): array => app(ViewCloudflareMailSuppression::class)->getHeaderActions(), null, ViewCloudflareMailSuppression::class)();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->getName())->toBe('refresh')
        ->and($actions[0]->getLabel())->toBe('Refresh suppressions');
});

it('refreshes the current suppression record after fetching', function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);

    $cloudflareMailSuppression = CloudflareMailSuppression::query()->create([
        'suppression_id' => 'suppression-1',
        'zone_id' => 'zone-1',
        'zone_name' => 'example.com',
        'email' => 'person@example.com',
        'reason' => 'hard_bounce',
        'suppressed_at' => CarbonImmutable::parse('2026-04-25T10:00:00Z'),
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/email/sending/suppression*' => Http::response([
            'page' => 1,
            'per_page' => 100,
            'total' => 1,
            'result' => [[
                'id' => 'suppression-1',
                'email' => 'person@example.com',
                'reason' => 'manual',
                'created_at' => '2026-04-25T10:00:00Z',
                'expires_at' => null,
                'zones' => ['example.com'],
            ]],
        ]),
    ]);

    $viewCloudflareMailSuppression = app(ViewCloudflareMailSuppression::class);
    $viewCloudflareMailSuppression->record = $cloudflareMailSuppression;
    $viewCloudflareMailSuppression->refreshCloudflareMailSuppressions();

    expect($viewCloudflareMailSuppression->record->reason)->toBe('manual');
});

it('builds the email suppression infolist', function (): void {
    $schema = CloudflareMailSuppressionResource::infolist(Schema::make());
    $components = $schema->getComponents();

    expect($components)->not->toBeEmpty()
        ->and(CloudflareMailSuppressionResource::formatStringList(['example.com', 123, []]))->toBe('example.com, 123')
        ->and(CloudflareMailSuppressionResource::formatStringList('not-a-list'))->toBeNull()
        ->and(CloudflareMailSuppressionResource::formatRawPayload(['foo' => 'bar']))->toBe("{\n    \"foo\": \"bar\"\n}")
        ->and(CloudflareMailSuppressionResource::formatRawPayload('{"foo":"bar"}'))->toBe("{\n    \"foo\": \"bar\"\n}")
        ->and(CloudflareMailSuppressionResource::formatRawPayload(7))->toBe('7')
        ->and(CloudflareMailSuppressionResource::formatRawPayload(new class implements Stringable
        {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        }))->toBe('stringable-value')
        ->and(CloudflareMailSuppressionResource::formatRawPayload(new stdClass))->toBeNull()
        ->and(CloudflareMailSuppressionResource::formatRawPayload(null))->toBeNull();
});

it('boots the plugin without side effects', function (): void {
    $cloudflareMailMonitorPlugin = CloudflareMailMonitorPlugin::make();
    $panel = Panel::make()->id('admin');

    $cloudflareMailMonitorPlugin->boot($panel);

    expect($cloudflareMailMonitorPlugin->getId())->toBe('cloudflare-mail-monitor');
});
