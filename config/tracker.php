<?php

return [

    'api_key_hash' => env('TRACKER_API_KEY_HASH'),

    'logging_enabled' => (bool) env('TRACKER_LOGGING', env('APP_DEBUG', false)),

    'allowed_action_types' => [
        'category_view',
        'product_view',
        'product_view_popup',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'payment_success',
    ],

    'payment_success_allowed_keys' => [
        'order_id',
        'amount_paid',
        'payment_method',
        'currency',
        'checkout_info',
    ],

    'scalar_field_limits' => [
        'category_name' => 255,
        'category_code' => 100,
        'product_name' => 255,
        'product_code' => 100,
        'product_color_id' => 50,
        'product_color_code' => 255,
        'general_color_name' => 255,
    ],

    'session_gap_minutes' => (int) env('TRACKER_SESSION_GAP_MINUTES', 30),

    'visitor_timezone' => env('TRACKER_VISITOR_TIMEZONE', 'Europe/London'),

    'visitor_cookie_name' => 'enox_visitor_id',

    /*
    |--------------------------------------------------------------------------
    | Tracker Redis
    |--------------------------------------------------------------------------
    |
    | Visitor session state uses a dedicated Redis connection (database.php
    | redis.tracker). This is separate from Laravel CACHE_STORE / app cache.
    |
    */

    'redis_connection' => env('TRACKER_REDIS_CONNECTION', 'tracker'),

    'redis_use_memory_store' => (bool) env('TRACKER_REDIS_USE_MEMORY_STORE', false),

    'redis_prefix' => env('TRACKER_REDIS_PREFIX', 'enox:tracker:'),

        'redis_ttl_seconds' => (int) env('TRACKER_REDIS_TTL_SECONDS', 172800),

    'visitor_seen_ttl_seconds' => (int) env('TRACKER_VISITOR_SEEN_TTL_SECONDS', 31536000),

    'rollup_lock_seconds' => (int) env('TRACKER_ROLLUP_LOCK_SECONDS', 45),

    'queue_connection' => env('TRACKER_QUEUE_CONNECTION', 'tracker'),

    'queue_name' => env('TRACKER_QUEUE_NAME', 'tracker'),

    'queue_async' => (bool) env('TRACKER_QUEUE_ASYNC', true),

    'analytics_cache_enabled' => (bool) env('TRACKER_ANALYTICS_CACHE_ENABLED', true),

    'analytics_cache_ttl_seconds' => (int) env('TRACKER_ANALYTICS_CACHE_SECONDS', 300),

    'analytics_windows' => [
        'hours' => [1, 3, 6, 12, 24],
        'days' => [1, 7, 14, 30, 90],
        'weeks' => [1, 4, 12, 52],
        'months' => [1, 3, 6, 12],
        'years' => [1],
    ],

];
