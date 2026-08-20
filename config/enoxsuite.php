<?php

return [
    'super_admin_username' => env('SUPER_ADMIN_USERNAME'),
    'super_admin_password' => env('SUPER_ADMIN_PASSWORD', 'password'),
    'internal_api' => [
        'keys' => explode(',', env('INTERNAL_API_KEYS')),
        'allowed_ips' => explode(',', env('INTERNAL_API_ALLOWED_IPS')),
    ],
];
