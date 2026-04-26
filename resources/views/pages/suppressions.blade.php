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
            Cloudflare Email Service suppressions are loaded directly from the configured zones using the Email Sending suppression API. These records are not stored locally.
        </p>
    </x-filament::section>

    <x-filament::section>
        @if ($this->suppressionRows() === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">No suppressions were returned for the configured zones.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left font-semibold text-gray-950 dark:text-white">
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Reason</th>
                            <th class="px-3 py-2">Configured zone</th>
                            <th class="px-3 py-2">Cloudflare zones</th>
                            <th class="px-3 py-2">Created</th>
                            <th class="px-3 py-2">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($this->suppressionRows() as $suppression)
                            <tr class="text-gray-700 dark:text-gray-200">
                                <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $suppression['email'] }}</td>
                                <td class="px-3 py-2">{{ $suppression['reason'] }}</td>
                                <td class="px-3 py-2">{{ $suppression['zone_name'] }}</td>
                                <td class="px-3 py-2">{{ $suppression['zones_display'] ?: 'None' }}</td>
                                <td class="px-3 py-2">{{ $suppression['created_at'] }}</td>
                                <td class="px-3 py-2">{{ $suppression['expires_at'] ?? 'Never' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
