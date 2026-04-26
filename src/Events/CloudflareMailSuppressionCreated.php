<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Events;

use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class CloudflareMailSuppressionCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public CloudflareMailSuppression $suppression,
    ) {}
}
