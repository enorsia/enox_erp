<?php

return [

    // Default guard
    'default_guard' => env('PERMISSION_DEFAULT_GUARD', config('auth.defaults.guard', 'web')),

    // Permission map
    'map' => [
        // USERS MODULE
        'users' => [
            'customers' => [
                'guard' => 'web',
                'actions' => ['index', 'show', 'status'],
            ]
        ],
        // INVENTORY MODULE
        'inventory' => [
            'attribute' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete', 'status'],
            ],
            'category' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete', 'status'],
            ],
            'product' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete', 'export', 'status'],
            ],
            'inventory' => [
                'guard' => 'web',
                'actions' => ['index', 'edit', 'export'],
            ],
        ],

        // AUTHENTICATION MODULE
        'authentication' => [
            'web' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete'],
            ],
            'role' => [
                'guard' => 'web',
                'actions' => ['index', 'create', 'edit', 'show', 'delete'],
            ],

        ],

        // ORDER MODULE
        'order' => [
            'order' => [
                'guard' => 'web',
                'actions' => ['index', 'show', 'edit', 'status', 'payment', 'case'],
            ],
        ],
        // SUPPORT MODULE
        'support' => [
            'notification' => [
                'guard' => 'web',
                'actions' => ['index', 'show', 'delete', 'update'],
            ],
            'messages' => [
                'guard' => 'web',
                'actions' => ['index'],
            ],
            'order_messages' => [
                'guard' => 'web',
                'actions' => ['index'],
            ],
        ],

    ],
];
