<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ClickHouse Connection Settings
    |--------------------------------------------------------------------------
    |
    | Used exclusively for the tracking analytics system.
    | This does NOT affect the main MySQL database.
    |
    */

    'host' => env('CLICKHOUSE_HOST', '127.0.0.1'),
    'port' => env('CLICKHOUSE_HTTP_PORT', 8123),
    'database' => env('CLICKHOUSE_DATABASE', 'enorsia_analytics'),
    'username' => env('CLICKHOUSE_USERNAME', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', 'admin123'),
    'timeout' => env('CLICKHOUSE_TIMEOUT', 5),

];

