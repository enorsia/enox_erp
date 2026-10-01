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

    /**
     * When true, dashboard reads closed calendar days from activity_ecom_daily_* rollups
     * and only scans raw tables for today (store timezone). Rollups never replace ingest.
     */
    'use_daily_rollups' => (bool) env('TRACKER_USE_DAILY_ROLLUPS', true),

    /**
     * Store dashboard: closed days come only from activity_ecom_daily_* rollups.
     * Today stays live on raw tables. If any closed day in the range lacks a site rollup row, the dashboard shows no metrics (no slow raw fallback).
     */
    'dashboard_rollups_only' => (bool) env('TRACKER_DASHBOARD_ROLLUPS_ONLY', true),

    /**
     * When true, hybrid dashboard catalog reads product/category daily rollups (migration 2026_09_28_000007).
     * Set false only before that migration runs; avoids information_schema probes per request.
     */
    'daily_rollups_commerce_view_columns' => filter_var(
        env('TRACKER_DAILY_ROLLUPS_COMMERCE_VIEW_COLUMNS', true),
        FILTER_VALIDATE_BOOL,
    ),

    /**
     * When true and batch snapshot is unavailable, recoverable-sale panels show counts only (no session table).
     * With batch read enabled, rows are hydrated from the snapshot without extra queries.
     */
    'dashboard_fast_recovery_rows' => (bool) env('TRACKER_DASHBOARD_FAST_RECOVERY_ROWS', false),

    /** One batched DB read for the unfiltered store dashboard (date range only). */
    'dashboard_batch_read' => (bool) env('TRACKER_DASHBOARD_BATCH_READ', true),

    /**
     * For long rollup-backed ranges (7d/30d), skip loading every session/line item into memory.
     * Recoverable panels use SQL; counts and rollups stay the same.
     */
    /** Use slim rollup batch when closed days >= this (yesterday = 1 closed day when today is live). */
    'dashboard_slim_batch_min_closed_days' => (int) env('TRACKER_DASHBOARD_SLIM_BATCH_MIN_CLOSED_DAYS', 1),

    /**
     * Visitor quality (bot) strip on store dashboard — one extra SQL scan when true.
     * Activity list / visitor analytics are unchanged.
     */
    'dashboard_visitor_quality' => filter_var(env('TRACKER_DASHBOARD_VISITOR_QUALITY', false), FILTER_VALIDATE_BOOL),

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

    'analytics_cache_enabled' => (bool) env('TRACKER_ANALYTICS_CACHE_ENABLED', false),

    'analytics_cache_ttl_seconds' => (int) env('TRACKER_ANALYTICS_CACHE_SECONDS', 300),

    'analytics_cache_today_ttl_seconds' => (int) env('TRACKER_ANALYTICS_CACHE_TODAY_SECONDS', 60),

    'rollups_exclude_bots' => (bool) env('TRACKER_ROLLUPS_EXCLUDE_BOTS', true),

    'rollups_aggregate_by_catalog_ids' => (bool) env('TRACKER_ROLLUPS_AGGREGATE_BY_CATALOG_IDS', false),

    'action_sync_batch_size' => (int) env('TRACKER_ACTION_SYNC_BATCH_SIZE', 25),

    'action_sync_max_attempts' => (int) env('TRACKER_ACTION_SYNC_MAX_ATTEMPTS', 5),

    /*
     * Dashboard action sync uses the DB `jobs` table by default (QUEUE_CONNECTION=database).
     * Visitor resolve jobs still use TRACKER_QUEUE_* (Redis). Do not mix them unless intended.
     */
    'action_sync_queue_connection' => env('TRACKER_ACTION_SYNC_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'action_sync_queue_name' => env('TRACKER_ACTION_SYNC_QUEUE_NAME', env('DB_QUEUE', 'default')),

    /**
     * When true, the dashboard status poll runs one database queue job if batches are
     * queued but nothing is reserved (no queue:work process). Disable if you always
     * run a dedicated worker and want polls to stay read-only.
     */
    'action_sync_process_on_status_poll' => filter_var(
        env('TRACKER_ACTION_SYNC_PROCESS_ON_STATUS_POLL', true),
        FILTER_VALIDATE_BOOL,
    ),

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
        'klaviyo' => 'Klaviyo',
        'mailchimp' => 'Mailchimp',
        'omnisend' => 'Omnisend',
        'hubspot' => 'HubSpot',
        'brevo' => 'Brevo',
        'iterable' => 'Iterable',
        'customerio' => 'Customer.io',
        'attentive' => 'Attentive',
        'postscript' => 'Postscript',
        'dotdigital' => 'Dotdigital',
        'activecampaign' => 'ActiveCampaign',
        'salesforce' => 'Salesforce MC',
        'reddit' => 'Reddit',
        'yahoo' => 'Yahoo',
        'impact' => 'Impact',
        'cj' => 'CJ Affiliate',
        'shareasale' => 'ShareASale',
        'rakuten' => 'Rakuten',
        'partnerize' => 'Partnerize',
        '(direct)' => 'Direct',
    ],

    /*
    |--------------------------------------------------------------------------
    | UTM source aliases (stored as canonical keys above)
    |--------------------------------------------------------------------------
    */
    'utm_source_aliases' => [
        // Social / paid (common short utm_source values in ad links)
        'fb' => 'facebook',
        'fbook' => 'facebook',
        'face' => 'facebook',
        'meta' => 'facebook',
        'ig' => 'instagram',
        'insta' => 'instagram',
        'yt' => 'youtube',
        'tt' => 'tiktok',
        'tik' => 'tiktok',
        'tok' => 'tiktok',
        'tik-tok' => 'tiktok',
        'x' => 'twitter',
        'tw' => 'twitter',
        'twitter' => 'twitter',
        'pin' => 'pinterest',
        'li' => 'linkedin',
        'link' => 'linkedin',
        'ln' => 'linkedin',
        'in' => 'linkedin',
        'snap' => 'snapchat',
        'sc' => 'snapchat',
        'rd' => 'reddit',
        'redd' => 'reddit',
        'ms' => 'bing',
        'goog' => 'google',
        'adwords' => 'google',
        'googleads' => 'google',
        'google_ads' => 'google',
        'gads' => 'google',
        'yahoo' => 'yahoo',
        'ycl' => 'yahoo',
        // Affiliate
        'aw' => 'awin',
        'sas' => 'shareasale',
        'share-a-sale' => 'shareasale',
        'rak' => 'rakuten',
        'impactradius' => 'impact',
        // Email / CRM / SMS
        'kv' => 'klaviyo',
        'kl' => 'klaviyo',
        'mc' => 'mailchimp',
        'omni' => 'omnisend',
        'hs' => 'hubspot',
        'cio' => 'customerio',
        'att' => 'attentive',
        'ps' => 'postscript',
        'sfmc' => 'salesforce',
        'ac' => 'activecampaign',
        'dd' => 'dotdigital',
        'sendinblue' => 'brevo',
        'generic' => 'email',
        'newsletter' => 'email',
    ],

    // Last-touch attribution window (days) for all platforms on conversion_* — paid, email, affiliate, etc.
    'conversion_window_days' => (int) env('TRACKER_CONVERSION_WINDOW_DAYS', 7),

    'attribution_touch_log_retention_days' => (int) env('TRACKER_ATTRIBUTION_TOUCH_LOG_RETENTION_DAYS', 90),

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
