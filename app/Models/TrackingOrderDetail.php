<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingOrderDetail extends Model
{
    protected $fillable = [
        'tracking_order_id',
        'tracking_product_id',
        'tracking_category_id',
        'tracking_color_id',
        'tracking_size_id',
        'item_variant',
        'qty',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'tracking_order_id' => 'integer',
            'tracking_product_id' => 'integer',
            'tracking_category_id' => 'integer',
            'tracking_color_id' => 'integer',
            'tracking_size_id' => 'integer',
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TrackingOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TrackingProduct::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrackingCategory::class);
    }
}
