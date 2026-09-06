<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityEcomOrder extends Model
{
    protected $table = 'activity_ecom_orders';

    protected $fillable = [
        'order_id',
        'order_pk',
        'event_id',
        'session_id',
        'visitor_id',
        'amount_paid',
        'subtotal',
        'grand_total',
        'currency',
        'payment_method',
        'item_qty',
        'shipping_charge',
        'service_charge',
        'extra_charges_total',
        'discount_amount',
        'coupon_discount',
        'scs_discount',
        'sms_discount',
        'discount_type',
        'coupon_code',
        'customer_email',
        'customer_phone',
        'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'shipping_charge' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'extra_charges_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'scs_discount' => 'decimal:2',
            'sms_discount' => 'decimal:2',
            'ordered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivityEcomUser::class, 'session_id', 'session_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ActivityEcomCommerceLineItem::class, 'order_id', 'order_id');
    }
}
