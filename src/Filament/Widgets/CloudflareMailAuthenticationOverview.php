<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Widgets;

use DrAliRagab\FilamentCloudflareMailMonitor\Services\CloudflareMailSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CloudflareMailAuthenticationOverview extends StatsOverviewWidget
{
    /**
     * @return array<Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        $health = app(CloudflareMailSummary::class)->authenticationHealth();

        return [
            Stat::make('Authenticated', number_format($health['all_pass']))->color('success'),
            Stat::make('DKIM failures', number_format($health['dkim_fail']))->color($health['dkim_fail'] > 0 ? 'danger' : 'success'),
            Stat::make('DMARC failures', number_format($health['dmarc_fail']))->color($health['dmarc_fail'] > 0 ? 'danger' : 'success'),
            Stat::make('SPF failures', number_format($health['spf_fail']))->color($health['spf_fail'] > 0 ? 'danger' : 'success'),
        ];
    }
}
