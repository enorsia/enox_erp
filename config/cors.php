<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['POST', 'OPTIONS'],

//    'allowed_origins' => array_filter(
//        array_map('trim', explode(',', env('TRACKER_CORS_ORIGINS', 'http://localhost:8000,http://127.0.0.1:8000')))
//    ),

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
