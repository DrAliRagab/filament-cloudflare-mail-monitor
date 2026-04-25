<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets;

use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CloudflareMailStatsOverview extends StatsOverviewWidget
{
    /**
     * @return array<Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        $totals = app(CloudflareMailSummary::class)->totals();

        return [
            Stat::make('Total events', number_format($totals['total'])),
            Stat::make('Delivered', number_format($totals['delivered']))->color('success'),
            Stat::make('Delivery failed', number_format($totals['failed']))->color('danger'),
            Stat::make('Spam or NDR', number_format($totals['spam_or_ndr']))->color('warning'),
        ];
    }
}
