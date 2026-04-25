<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\DateRange;
use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailAggregateFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('cloudflare-mail-monitor.api.token', 'secret-token');
    config()->set('cloudflare-mail-monitor.fetch.page_size', 50);
    config()->set('cloudflare-mail-monitor.zones', [
        ['id' => 'zone-1', 'name' => 'example.com'],
    ]);
});

it('fetches aggregate email sending status counts from Cloudflare', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptiveGroups' => [[
                            'count' => 12,
                            'dimensions' => [
                                'date' => '2026-04-25',
                                'status' => 'delivered',
                            ],
                        ]],
                    ]],
                ],
            ],
        ]),
    ]);

    $metrics = app(CloudflareMailAggregateFetcher::class)->statusCounts(new DateRange(
        CarbonImmutable::parse('2026-04-01T00:00:00Z'),
        CarbonImmutable::parse('2026-04-25T00:00:00Z'),
    ));

    expect($metrics)->toHaveCount(1)
        ->and($metrics[0]->zone->id)->toBe('zone-1')
        ->and($metrics[0]->zone->name)->toBe('example.com')
        ->and($metrics[0]->date->toDateString())->toBe('2026-04-25')
        ->and($metrics[0]->status)->toBe('delivered')
        ->and($metrics[0]->count)->toBe(12);

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();
        $variables = $payload['variables'] ?? [];

        return is_string($payload['query'] ?? null)
            && str_contains($payload['query'], 'emailSendingAdaptiveGroups')
            && is_array($variables)
            && ($variables['zoneTag'] ?? null) === 'zone-1'
            && ($variables['start'] ?? null) === '2026-04-01'
            && ($variables['end'] ?? null) === '2026-04-25'
            && ($variables['limit'] ?? null) === 50;
    });
});

it('ignores malformed aggregate metric lists and entries', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-25T00:00:00Z'));

    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptiveGroups' => [
                            'not-a-group',
                            [
                                'count' => '7',
                                'dimensions' => [
                                    'date' => '2026-04-24',
                                    'status' => '',
                                ],
                            ],
                            [
                                'count' => [],
                                'dimensions' => 'not-dimensions',
                            ],
                        ],
                    ]],
                ],
            ],
        ]),
    ]);

    $metrics = app(CloudflareMailAggregateFetcher::class)->statusCounts();

    expect($metrics)->toHaveCount(2)
        ->and($metrics[0]->count)->toBe(7)
        ->and($metrics[0]->status)->toBeNull()
        ->and($metrics[1]->count)->toBe(0)
        ->and($metrics[1]->date->toDateString())->toBe('2026-04-25');

    CarbonImmutable::setTestNow();
});

it('returns no aggregate metrics when Cloudflare returns a malformed groups payload', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [[
                        'emailSendingAdaptiveGroups' => 'not-a-list',
                    ]],
                ],
            ],
        ]),
    ]);

    expect(app(CloudflareMailAggregateFetcher::class)->statusCounts())->toBe([]);
});
