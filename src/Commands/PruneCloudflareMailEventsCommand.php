<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Commands;

use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class PruneCloudflareMailEventsCommand extends Command
{
    protected $signature = 'cloudflare-mail-monitor:prune';

    protected $description = 'Prune stored Cloudflare mail events and expired suppressions.';

    public function handle(): int
    {
        Artisan::call('model:prune', [
            '--model' => [CloudflareMailEvent::class, CloudflareMailSuppression::class],
        ]);

        $this->output->write(Artisan::output());

        return self::SUCCESS;
    }
}
