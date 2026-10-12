<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDailyCategory extends Model
{
    protected $fillable = [
        'metric_date',
        'tracking_category_id',
        'category_views',
        'product_views',
        'add_to_carts',
        'proceed_checkouts',
        'sold_qty',
        'orders',
        'sale_amount',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'tracking_category_id' => 'integer',
            'category_views' => 'integer',
            'product_views' => 'integer',
            'add_to_carts' => 'integer',
            'proceed_checkouts' => 'integer',
            'sold_qty' => 'integer',
            'orders' => 'integer',
            'sale_amount' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrackingCategory::class, 'tracking_category_id');
    }
}
