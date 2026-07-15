<?php

return [

    'api_key_hash' => env('TRACKER_API_KEY_HASH'),

    'logging_enabled' => (bool) env('TRACKER_LOGGING', env('APP_DEBUG', false)),

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
        'product_name' => 255,
        'product_code' => 100,
        'product_color_id' => 50,
        'product_color_code' => 255,
        'general_color_name' => 255,
    ],

];
