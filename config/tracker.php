<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ecom Tracker (admin UI + permissions)
    |--------------------------------------------------------------------------
    |
    | When false, Ecom Tracker is hidden from the admin sidebar, role permission
    | screens, and web routes. Set ECOM_TRACKER_ENABLED=true to re-enable.
    |
    */
    'enabled' => (bool) env('ECOM_TRACKER_ENABLED', false),

    'api_key_hash' => env('TRACKER_API_KEY_HASH'),

    /*
    | Max events accepted in a single /api/track payload. The storefront
    | tracker must chunk larger queues to this size (MAX_EVENTS_PER_FLUSH).
    */
    'ingest_max_events' => (int) env('TRACKER_INGEST_MAX_EVENTS', 50),

    'logging_enabled' => (bool) env('TRACKER_LOGGING', env('APP_DEBUG', false)),

    'log_channel' => env('TRACKER_LOG_CHANNEL', 'ecom_tracker'),

    'log_days' => (int) env('TRACKER_LOG_DAYS', 30),

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
        'department_name' => 255,
        'product_name' => 255,
        'product_code' => 100,
        'sku' => 100,
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

    'commerce_sync_batch_size' => (int) env('TRACKER_COMMERCE_SYNC_BATCH_SIZE', 100),

    'commerce_sync_chunk_days' => (int) env('TRACKER_COMMERCE_SYNC_CHUNK_DAYS', 7),

    'analytics_windows' => [
        'hours' => [1, 3, 6, 12, 24],
        'days' => [1, 7, 14, 30, 90],
        'weeks' => [1, 4, 12, 52],
        'months' => [1, 3, 6, 12],
        'years' => [1],
    ],

    /*
    |--------------------------------------------------------------------------
    | UTM filter dropdowns (key => label)
    |--------------------------------------------------------------------------
    |
    | Keys should match values stored on activity_ecom_user.utm_source / utm_medium.
    | Use (direct) and none for empty traffic in analytics.
    |
    */

    'utm_sources' => [
        'google' => 'Google',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'awin' => 'Awin',
        'bing' => 'Bing',
        'pinterest' => 'Pinterest',
        'linkedin' => 'LinkedIn',
        'twitter' => 'Twitter / X',
        'snapchat' => 'Snapchat',
        'email' => 'Email',
        '(direct)' => 'Direct',
    ],

    /*
    |--------------------------------------------------------------------------
    | UTM source aliases (stored as canonical keys above)
    |--------------------------------------------------------------------------
    */
    'utm_source_aliases' => [
        'fb' => 'facebook',
        'meta' => 'facebook',
        'ig' => 'instagram',
        'insta' => 'instagram',
        'yt' => 'youtube',
        'tt' => 'tiktok',
        'x' => 'twitter',
        'pin' => 'pinterest',
        'li' => 'linkedin',
        'snap' => 'snapchat',
        'ms' => 'bing',
        'aw' => 'awin',
    ],

    'utm_mediums' => [
        'organic' => 'Organic',
        'cpc' => 'CPC (paid search)',
        'social' => 'Social',
        'email' => 'Email',
        'referral' => 'Referral',
        'affiliate' => 'Affiliate',
        'display' => 'Display',
        'video' => 'Video',
        'paid' => 'Paid',
        'cpm' => 'CPM',
        'awin' => 'Awin',
        'none' => 'None',
    ],

];
