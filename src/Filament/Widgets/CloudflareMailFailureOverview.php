<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets;

use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CloudflareMailFailureOverview extends StatsOverviewWidget
{
    /**
     * @return array<Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        $failures = app(CloudflareMailSummary::class)->deliveryFailures();
        $topErrorCause = $failures['top_error_cause'] ?? 'None recorded';

        return [
            Stat::make('Failed or rejected', number_format($failures['failed']))->color('danger'),
            Stat::make('Rejected', number_format($failures['rejected']))->color('warning'),
            Stat::make('NDR events', number_format($failures['ndr']))->color('warning'),
            Stat::make('Top failure cause', $topErrorCause)
                ->description(number_format($failures['top_error_cause_count']).' event(s)')
                ->color($failures['top_error_cause_count'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
