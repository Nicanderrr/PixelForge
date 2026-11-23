<?php

return [
    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),
    'api_key' => env('PIXELFORGE_API_KEY'),
    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),
    // Shared across keys of this provider account. Use the same value across app nodes.
    'account_scope' => env('PIXELFORGE_ACCOUNT_SCOPE', 'primary'),
    'max_attempts' => 8,
    'requests_per_minute' => 50, // Leave headroom for the provider dashboard / reconciliation.
    'concurrency' => 3,
];
