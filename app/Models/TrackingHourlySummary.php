<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingHourlySummary extends Model
{
    use UsesTrackingDatabase;

    protected $fillable = [
        'metric_date',
        'metric_hour',
        'unique_visitors',
        'sessions',
        'category_views',
        'product_views',
        'product_view_units',
        'product_add_units',
        'product_begin_units',
        'product_proceed_units',
        'product_paid_units',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'metric_hour' => 'integer',
            'unique_visitors' => 'integer',
            'sessions' => 'integer',
            'category_views' => 'integer',
            'product_views' => 'integer',
            'product_view_units' => 'integer',
            'product_add_units' => 'integer',
            'product_begin_units' => 'integer',
            'product_proceed_units' => 'integer',
            'product_paid_units' => 'integer',
        ];
    }
}
