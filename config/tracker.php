<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ecom Tracker (admin UI + permissions)
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('ECOM_TRACKER_ENABLED', false),

    'api_key_hash' => env('TRACKER_API_KEY_HASH'),

    /*
    | Max events per /api/track payload (storefront must chunk to this size).
    */
    'ingest_max_events' => (int) env('TRACKER_INGEST_MAX_EVENTS', 50),

    'logging_enabled' => (bool) env('TRACKER_LOGGING', env('APP_DEBUG', false)),

    'log_channel' => env('TRACKER_LOG_CHANNEL', 'ecom_tracker'),

    'allowed_action_types' => [
        'category_view',
        'product_view',
        'product_view_popup',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'payment_success',
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

    /*
    |--------------------------------------------------------------------------
    | Tracker Redis (see database.php redis.tracker)
    |--------------------------------------------------------------------------
    */
    'redis_connection' => env('TRACKER_REDIS_CONNECTION', 'tracker'),

    'redis_use_memory_store' => (bool) env('TRACKER_REDIS_USE_MEMORY_STORE', false),

    'redis_ttl_seconds' => (int) env('TRACKER_REDIS_TTL_SECONDS', 172800),

    'visitor_seen_ttl_seconds' => (int) env('TRACKER_VISITOR_SEEN_TTL_SECONDS', 31536000),

    'rollup_lock_seconds' => (int) env('TRACKER_ROLLUP_LOCK_SECONDS', 45),

    /** Exclude bot sessions from commerce funnel SQL when true. */
    'dashboard_exclude_bots' => filter_var(
        env('TRACKER_DASHBOARD_EXCLUDE_BOTS', true),
        FILTER_VALIDATE_BOOL,
    ),

    'queue_connection' => env('TRACKER_QUEUE_CONNECTION', 'tracker'),

    'queue_name' => env('TRACKER_QUEUE_NAME', 'tracker'),

    'queue_async' => (bool) env('TRACKER_QUEUE_ASYNC', true),

    'analytics_cache_enabled' => (bool) env('TRACKER_ANALYTICS_CACHE_ENABLED', false),

    'analytics_cache_ttl_seconds' => (int) env('TRACKER_ANALYTICS_CACHE_SECONDS', 300),

    /*
    | Dashboard sync → MySQL `jobs` table (database queue).
    */
    'dashboard_sync_queue_connection' => env('TRACKER_DASHBOARD_SYNC_QUEUE_CONNECTION', 'database'),

    'dashboard_sync_queue_name' => env('TRACKER_DASHBOARD_SYNC_QUEUE_NAME', env('DB_QUEUE', 'default')),

    'analytics_windows' => [
        'hours' => [1, 3, 6, 12, 24],
        'days' => [1, 7, 14, 30, 90],
        'weeks' => [1, 4, 12, 52],
        'months' => [1, 3, 6, 12],
        'years' => [1],
    ],

    'conversion_window_days' => (int) env('TRACKER_CONVERSION_WINDOW_DAYS', 7),

    'attribution_touch_log_retention_days' => (int) env('TRACKER_ATTRIBUTION_TOUCH_LOG_RETENTION_DAYS', 90),

    /*
    | UTM filters (activity_ecom_user.utm_source / utm_medium)
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

    'utm_source_aliases' => [
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
        'aw' => 'awin',
        'sas' => 'shareasale',
        'share-a-sale' => 'shareasale',
        'rak' => 'rakuten',
        'impactradius' => 'impact',
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
