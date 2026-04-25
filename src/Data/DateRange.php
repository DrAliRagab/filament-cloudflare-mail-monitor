<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($this->start->greaterThanOrEqualTo($this->end)) {
            throw new InvalidArgumentException('Date range start must be before the end.');
        }
    }

    public static function forLookbackDays(int $days, int $maxDays, ?CarbonImmutable $now = null): self
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Lookback days must be at least 1.');
        }

        if ($days > $maxDays) {
            throw new InvalidArgumentException("Lookback days cannot exceed {$maxDays} days.");
        }

        $end = ($now ?? CarbonImmutable::now('UTC'))->utc();

        return new self(
            start: $end->subDays($days),
            end: $end,
        );
    }
}
