<?php

return [
    'base_url' => env('ENOX_API_BASE_URL'),
    'api_key' => env('ENOX_API_KEYS'),
    'timeout' => 10,
    'retry' => 2,
    'headers' => [
        'Accept' => 'application/json',
        'X-INTERNAL-KEY' => env('ENOX_API_KEY'),
    ],
    'endpoints' => [
        'fabrications' => 'fabrications',
        'fabrications_store' => 'fabrications/store',
    ]
];
