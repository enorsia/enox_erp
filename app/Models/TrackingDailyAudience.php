<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingDailyAudience extends Model
{
    public const TYPE_DEVICE = 1;

    public const TYPE_BROWSER = 2;

    public const TYPE_TRAFFIC_SOURCE = 3;

    /** type => lookup table that dimension_id points to */
    public const DIMENSION_TABLES = [
        self::TYPE_DEVICE => 'tracking_device',
        self::TYPE_BROWSER => 'tracking_browser',
        self::TYPE_TRAFFIC_SOURCE => 'tracking_traffic_source',
    ];

    protected $fillable = [
        'metric_date',
        'type',
        'dimension_id',
        'source',
        'medium',
        'sessions',
        'views',
        'add_to_carts',
        'begin_checkouts',
        'proceed_checkouts',
        'payments',
        'sold_qty',
        'sale_amount',
        'conversion_rate',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'type' => 'integer',
            'dimension_id' => 'integer',
            'sessions' => 'integer',
            'views' => 'integer',
            'add_to_carts' => 'integer',
            'begin_checkouts' => 'integer',
            'proceed_checkouts' => 'integer',
            'payments' => 'integer',
            'sold_qty' => 'integer',
            'sale_amount' => 'decimal:2',
            'conversion_rate' => 'decimal:2',
        ];
    }
}
