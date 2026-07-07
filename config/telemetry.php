<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telemetria de requisições API
    |--------------------------------------------------------------------------
    */

    'enabled' => env('TELEMETRY_ENABLED', true),

    'log_trace' => env('TELEMETRY_LOG_TRACE', false),

    'retention_days' => (int) env('TELEMETRY_RETENTION_DAYS', 90),

    'skip_paths' => [
        'up',
        'docs/*',
        'sanctum/csrf-cookie',
    ],

    'skip_prefixes' => [
        'admin/',
    ],

];
