<x-filament-panels::page>
    @if ($warning = $this->configurationWarning())
        <x-filament::section>
            <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-900 dark:border-warning-700 dark:bg-warning-950/40 dark:text-warning-200">
                <strong class="block font-medium">Configuration needed</strong>
                <p class="mt-1">{{ $warning }}</p>
            </div>
        </x-filament::section>
    @endif

    @if ($warning = $this->authorizationWarning())
        <x-filament::section>
            <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-900 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-200">
                <strong class="block font-medium">Cloudflare permissions needed</strong>
                <p class="mt-1">{{ $warning }}</p>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Cloudflare Email Service outbound events are stored locally from Cloudflare GraphQL Analytics. Use the refresh action to fetch the latest configured lookback window on demand.
        </p>
    </x-filament::section>
</x-filament-panels::page>
