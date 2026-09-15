<?php

return [
    'report_chunk_size' => (int) env('EXPORT_REPORT_CHUNK_SIZE', 75),

    'queue' => env('EXPORT_QUEUE', 'default'),
    'queue_connection' => env('EXPORT_QUEUE_CONNECTION', 'database'),
    'file_ttl_hours' => (int) env('EXPORT_FILE_TTL_HOURS', 24),
    'download_url_expiry_minutes' => (int) env('EXPORT_DOWNLOAD_URL_EXPIRY_MINUTES', 15),
    'progress_update_interval_seconds' => (float) env('EXPORT_PROGRESS_UPDATE_INTERVAL', 1),

    'company_name' => env('EXPORT_COMPANY_NAME', 'PFD ENORSIA UK LTD'),

    'types' => [
        'ecom_activity_report' => [
            'label' => 'User Activity Report',
            'route' => 'admin.ecom-activity.index',
            'permission' => 'ecom_tracker.activity.index',
            'filename_prefix' => 'User Activity Report',
        ],
    ],
];
