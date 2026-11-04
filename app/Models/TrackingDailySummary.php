<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingDailySummary extends Model
{
    use UsesTrackingDatabase;

    protected $fillable = [
        'metric_date',
        'unique_visitors',
        'sessions',
        'stay_seconds',
        'category_views',
        'product_views',
        'product_view_units',
        'product_add_units',
        'product_begin_units',
        'product_proceed_units',
        'product_paid_units',
        'product_sale_amount',
        'cart_sessions',
        'checkout_sessions',
        'proceed_sessions',
        'cart_drops',
        'checkout_drops',
        'proceed_drops',
        'payments',
        'items_sold',
        'sale_amount',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'unique_visitors' => 'integer',
            'sessions' => 'integer',
            'stay_seconds' => 'integer',
            'category_views' => 'integer',
            'product_views' => 'integer',
            'product_view_units' => 'integer',
            'product_add_units' => 'integer',
            'product_begin_units' => 'integer',
            'product_proceed_units' => 'integer',
            'product_paid_units' => 'integer',
            'product_sale_amount' => 'decimal:2',
            'cart_sessions' => 'integer',
            'checkout_sessions' => 'integer',
            'proceed_sessions' => 'integer',
            'cart_drops' => 'integer',
            'checkout_drops' => 'integer',
            'proceed_drops' => 'integer',
            'payments' => 'integer',
            'items_sold' => 'integer',
            'sale_amount' => 'decimal:2',
        ];
    }
}
