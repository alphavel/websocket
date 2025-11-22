<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the WebSocket server host, port, and Swoole options.
    | These settings control connection handling, performance, and behavior.
    |
    */

    'host' => env('WEBSOCKET_HOST', '0.0.0.0'),
    'port' => env('WEBSOCKET_PORT', 9501),

    /*
    |--------------------------------------------------------------------------
    | Swoole Server Options
    |--------------------------------------------------------------------------
    |
    | Fine-tune Swoole server performance and behavior.
    | See: https://www.swoole.co.uk/docs/modules/swoole-server/configuration
    |
    */

    'options' => [
        'worker_num' => env('WEBSOCKET_WORKERS', swoole_cpu_num()),
        'task_worker_num' => env('WEBSOCKET_TASK_WORKERS', swoole_cpu_num()),
        'max_request' => 10000,
        'max_connection' => 100000,
        'heartbeat_check_interval' => 60,
        'heartbeat_idle_time' => 600,
        'package_max_length' => 2 * 1024 * 1024, // 2MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Driver
    |--------------------------------------------------------------------------
    |
    | Configure how messages are broadcast to clients.
    | Supported: "swoole-table", "redis"
    |
    */

    'broadcasting' => [
        'driver' => env('BROADCAST_DRIVER', 'swoole-table'),

        'swoole-table' => [
            'size' => 100000, // Max channels
        ],

        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_DATABASE', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure WebSocket authentication. Supports JWT tokens.
    |
    */

    'auth' => [
        'enabled' => env('WEBSOCKET_AUTH', true),
        'guard' => env('WEBSOCKET_GUARD', 'api'),
        'token_query_param' => 'token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence Channels
    |--------------------------------------------------------------------------
    |
    | Configure presence channel behavior (who's online tracking).
    |
    */

    'presence' => [
        'enabled' => true,
        'heartbeat_interval' => 30, // seconds
        'timeout' => 120, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure WebSocket server logging.
    |
    */

    'logging' => [
        'enabled' => env('WEBSOCKET_LOGGING', true),
        'level' => env('WEBSOCKET_LOG_LEVEL', 'info'),
        'file' => storage_path('logs/websocket.log'),
    ],
];
