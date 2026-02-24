<?php

return [

    // Default guard
    'default_guard' => env('PERMISSION_DEFAULT_GUARD', config('auth.defaults.guard', 'web')),

    // Permission map
    'map' => [

        // AUTHENTICATION MODULE
        'authentication' => [
            'users' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete'],
            ],
            'roles' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete'],
            ],
            'activity_logs' => [
                'guard' => 'web',
                'actions' => ['index', 'show'],
            ]

        ],

        // GENERAL MODULE
        'general' => [
            'dashboard' => [
                'guard' => 'web',
                'actions' => ['index'],
            ],
            'chart' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete', 'approve', 'import', 'bulk_edit', 'export'],
            ],
            'fabrication' => [
                'guard' => 'web',
                'actions' => ['index', 'create'],
            ],
            'expense' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'delete'],
            ],
            'forecasting' => [
                'guard' => 'web',
                'actions' => ['index', 'show'],
            ],
            'discounts' => [
                'guard' => 'web',
                'actions' => ['index', 'show', 'update', 'approve', 'sent_mail'],
            ],
        ],

        // SETTINGS MODULE
        'settings' => [
            'platforms' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'delete'],
            ]
        ],

    ],
];
