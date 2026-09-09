<?php

return [
    'super_admin_username' => env('SUPER_ADMIN_USERNAME'),
    'super_admin_password' => env('SUPER_ADMIN_PASSWORD', 'password'),
    'internal_api' => [
        'keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTERNAL_API_KEYS', ''))))),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTERNAL_API_ALLOWED_IPS', ''))))),
    ],
];
