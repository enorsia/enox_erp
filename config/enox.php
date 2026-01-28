<?php

return [
    'base_url' => env('ENOX_API_BASE_URL'),
    'timeout' => 10,
    'retry' => 2,
    'headers' => [
        'Accept' => 'application/json',
    ],
    'endpoints' => [
        'fabrications' => 'fabrications',
        'fabrications_store' => 'fabrications/store',
    ]
];
