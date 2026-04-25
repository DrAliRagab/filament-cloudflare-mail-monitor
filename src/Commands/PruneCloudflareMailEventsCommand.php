<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Commands;

use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class PruneCloudflareMailEventsCommand extends Command
{
    protected $signature = 'cloudflare-mail-monitor:prune';

    protected $description = 'Prune stored Cloudflare mail events using the package retention policy.';

    public function handle(): int
    {
        Artisan::call('model:prune', [
            '--model' => [CloudflareMailEvent::class],
        ]);

        $this->output->write(Artisan::output());

        return self::SUCCESS;
    }
}
