<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource\Pages;

use DrAliRagab\FilamentCloudflareMailMonitor\Filament\Resources\CloudflareMailEventResource;
use Filament\Resources\Pages\ListRecords;

final class ListCloudflareMailEvents extends ListRecords
{
    protected static string $resource = CloudflareMailEventResource::class;
}
