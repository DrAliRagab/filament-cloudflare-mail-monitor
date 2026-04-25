<?php

declare(strict_types=1);

return [
    'api' => [
        'base_url' => env('CLOUDFLARE_MAIL_MONITOR_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),
        'token' => env('CLOUDFLARE_MAIL_MONITOR_API_TOKEN'),
        'timeout' => (int) env('CLOUDFLARE_MAIL_MONITOR_API_TIMEOUT', 15),
        'retry_times' => (int) env('CLOUDFLARE_MAIL_MONITOR_API_RETRY_TIMES', 2),
        'retry_sleep_milliseconds' => (int) env('CLOUDFLARE_MAIL_MONITOR_API_RETRY_SLEEP', 250),
    ],

    'zones' => [
        [
            'id' => env('CLOUDFLARE_MAIL_MONITOR_ZONE_ID'),
            'name' => env('CLOUDFLARE_MAIL_MONITOR_ZONE_NAME'),
        ],
    ],

    'fetch' => [
        'lookback_days' => (int) env('CLOUDFLARE_MAIL_MONITOR_FETCH_LOOKBACK_DAYS', 1),
        'max_lookback_days' => (int) env('CLOUDFLARE_MAIL_MONITOR_MAX_LOOKBACK_DAYS', 31),
        'page_size' => (int) env('CLOUDFLARE_MAIL_MONITOR_FETCH_PAGE_SIZE', 500),
    ],

    'retention' => [
        'days' => (int) env('CLOUDFLARE_MAIL_MONITOR_RETENTION_DAYS', 90),
    ],

    'privacy' => [
        'mask_email_addresses' => (bool) env('CLOUDFLARE_MAIL_MONITOR_MASK_EMAILS', false),
        'show_subjects' => (bool) env('CLOUDFLARE_MAIL_MONITOR_SHOW_SUBJECTS', true),
    ],

    'filament' => [
        'navigation_group' => env('CLOUDFLARE_MAIL_MONITOR_NAVIGATION_GROUP', 'Cloudflare'),
        'dashboard_navigation_icon' => env('CLOUDFLARE_MAIL_MONITOR_DASHBOARD_NAVIGATION_ICON', 'heroicon-o-chart-bar'),
        'navigation_icon' => env('CLOUDFLARE_MAIL_MONITOR_NAVIGATION_ICON', 'heroicon-o-envelope'),
        'navigation_sort' => (int) env('CLOUDFLARE_MAIL_MONITOR_NAVIGATION_SORT', 90),
        'should_register_navigation' => (bool) env('CLOUDFLARE_MAIL_MONITOR_REGISTER_NAVIGATION', true),
    ],
];
