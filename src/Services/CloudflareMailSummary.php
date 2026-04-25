<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Services;

use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use Illuminate\Database\Eloquent\Builder;

final class CloudflareMailSummary
{
    /**
     * @return list<string>
     */
    public static function failureStatuses(): array
    {
        return ['deliveryFailed', 'failed', 'rejected'];
    }

    /**
     * @return array{total: int, delivered: int, failed: int, spam_or_ndr: int}
     */
    public function totals(): array
    {
        return [
            'total' => CloudflareMailEvent::query()->count(),
            'delivered' => CloudflareMailEvent::query()->where('status', 'delivered')->count(),
            'failed' => CloudflareMailEvent::query()->whereIn('status', self::failureStatuses())->count(),
            'spam_or_ndr' => CloudflareMailEvent::query()
                ->where(static fn (Builder $builder): Builder => $builder->where('is_spam', true)->orWhere('is_ndr', true))
                ->count(),
        ];
    }

    /**
     * @return array{failed: int, rejected: int, ndr: int, top_error_cause: string|null, top_error_cause_count: int}
     */
    public function deliveryFailures(): array
    {
        $topErrorCauses = CloudflareMailEvent::query()
            ->whereIn('status', self::failureStatuses())
            ->whereNotNull('error_cause')
            ->pluck('error_cause')
            ->filter(static fn (mixed $errorCause): bool => is_string($errorCause) && $errorCause !== '')
            ->countBy()
            ->sortDesc();

        $topErrorCause = $topErrorCauses->keys()->first();
        $topErrorCauseCount = $topErrorCauses->first();

        return [
            'failed' => CloudflareMailEvent::query()->whereIn('status', self::failureStatuses())->count(),
            'rejected' => CloudflareMailEvent::query()->where('status', 'rejected')->count(),
            'ndr' => CloudflareMailEvent::query()->where('is_ndr', true)->count(),
            'top_error_cause' => is_string($topErrorCause) ? $topErrorCause : null,
            'top_error_cause_count' => is_int($topErrorCauseCount) ? $topErrorCauseCount : 0,
        ];
    }

    /**
     * @return array{dkim_fail: int, dmarc_fail: int, spf_fail: int, all_pass: int}
     */
    public function authenticationHealth(): array
    {
        return [
            'dkim_fail' => CloudflareMailEvent::query()->where('dkim', 'fail')->count(),
            'dmarc_fail' => CloudflareMailEvent::query()->where('dmarc', 'fail')->count(),
            'spf_fail' => CloudflareMailEvent::query()->where('spf', 'fail')->count(),
            'all_pass' => CloudflareMailEvent::query()
                ->where('dkim', 'pass')
                ->where('dmarc', 'pass')
                ->where('spf', 'pass')
                ->count(),
        ];
    }
}
