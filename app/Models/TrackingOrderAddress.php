<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingOrderAddress extends Model
{
    public const TYPE_SHIPPING = 1;

    public const TYPE_BILLING = 2;

    protected $fillable = [
        'tracking_order_id',
        'type',
        'line_1',
        'line_2',
        'town_city',
        'postcode',
        'country',
    ];

    protected function casts(): array
    {
        return [
            'tracking_order_id' => 'integer',
            'type' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TrackingOrder::class);
    }
}
