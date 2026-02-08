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
        'selling_chart_ecom_products' => 'selling-chart/get-ecom-products',
        'selling_chart_lookup_names' => 'selling-chart/get-lookup-names',
        'selling_chart_product_categories' => 'selling-chart/get-product-categories',
        'selling_chart_color_by_search' => 'selling-chart/get-color-by-search',
        'selling_chart_sizes_by_category' => 'selling-chart/get-sizes-by-category',
        'selling_chart_po_histories' => 'selling-chart/get-po-histories',
    ]
];
