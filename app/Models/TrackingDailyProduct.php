<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingDailyProduct extends Model
{
    protected $fillable = [
        'metric_date',
        'product_code',
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
            'product_views' => 'integer',
            'add_to_carts' => 'integer',
            'proceed_checkouts' => 'integer',
            'sold_qty' => 'integer',
            'sale_amount' => 'decimal:2',
        ];
    }

    /** Every sku row of this product code. */
    public function products(): HasMany
    {
        return $this->hasMany(TrackingProduct::class, 'code', 'product_code');
    }
}
