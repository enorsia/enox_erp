<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrackingOrder extends Model
{
    protected $fillable = [
        'tracking_session_id',
        'order_number',
        'order_pk',
        'metric_date',
        'ordered_at',
        'first_name',
        'last_name',
        'email',
        'phone',
        'currency',
        'payment_method',
        'coupon_code',
        'items_qty',
        'subtotal',
        'shipping_cost',
        'delivery_charge',
        'service_charge',
        'priority_charge',
        'extra_handling_cost',
        'extra_charges_total',
        'discount_total',
        'grand_total',
        'amount_paid',
    ];

    protected function casts(): array
    {
        return [
            'tracking_session_id' => 'integer',
            'order_pk' => 'integer',
            'metric_date' => 'date',
            'ordered_at' => 'datetime',
            'items_qty' => 'integer',
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'priority_charge' => 'decimal:2',
            'extra_handling_cost' => 'decimal:2',
            'extra_charges_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TrackingOrderDetail::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(TrackingOrderAddress::class);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(TrackingOrderAddress::class)
            ->where('type', TrackingOrderAddress::TYPE_SHIPPING);
    }
}
