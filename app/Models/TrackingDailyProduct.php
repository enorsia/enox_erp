<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDailyProduct extends Model
{
    protected $fillable = [
        'metric_date',
        'tracking_product_id',
        'product_views',
        'add_to_carts',
        'proceed_checkouts',
        'sold_qty',
        'sale_amount',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'tracking_product_id' => 'integer',
            'product_views' => 'integer',
            'add_to_carts' => 'integer',
            'proceed_checkouts' => 'integer',
            'sold_qty' => 'integer',
            'sale_amount' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TrackingProduct::class, 'tracking_product_id');
    }
}
