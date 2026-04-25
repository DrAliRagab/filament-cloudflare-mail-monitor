<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Services;

use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use Illuminate\Database\Eloquent\Builder;

final class CloudflareMailSummary
{
    /**
     * @return array{total: int, delivered: int, failed: int, spam_or_ndr: int}
     */
    public function totals(): array
    {
        return [
            'total' => CloudflareMailEvent::query()->count(),
            'delivered' => CloudflareMailEvent::query()->where('status', 'delivered')->count(),
            'failed' => CloudflareMailEvent::query()->where('status', 'deliveryFailed')->count(),
            'spam_or_ndr' => CloudflareMailEvent::query()
                ->where(static fn (Builder $builder): Builder => $builder->where('is_spam', true)->orWhere('is_ndr', true))
                ->count(),
        ];
    }
}
