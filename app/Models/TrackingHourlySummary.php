<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingHourlySummary extends Model
{
    protected $fillable = [
        'metric_date',
        'metric_hour',
        'unique_visitors',
        'sessions',
        'stay_seconds',
        'category_views',
        'product_views',
        'cart_sessions',
        'checkout_sessions',
        'proceed_sessions',
        'payments',
        'items_sold',
        'sale_amount',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'metric_hour' => 'integer',
            'unique_visitors' => 'integer',
            'sessions' => 'integer',
            'stay_seconds' => 'integer',
            'category_views' => 'integer',
            'product_views' => 'integer',
            'cart_sessions' => 'integer',
            'checkout_sessions' => 'integer',
            'proceed_sessions' => 'integer',
            'payments' => 'integer',
            'items_sold' => 'integer',
            'sale_amount' => 'decimal:2',
        ];
    }
}
