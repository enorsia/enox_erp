<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEcomUserAction extends Model
{
    public const SYNC_PENDING = 0;

    public const SYNC_SYNCED = 1;

    public const SYNC_FAILED = 2;

    public $timestamps = false;

    protected $table = 'activity_ecom_user_actions';

    protected $fillable = [
        'event_id',
        'session_id',
        'action_type',
        'category_name',
        'category_code',
        'department_name',
        'product_name',
        'product_code',
        'sku',
        'product_color_id',
        'product_color_code',
        'general_color_name',
        'product_price',
        'page_url',
        'referer',
        'add_to_cart',
        'begin_checkout',
        'proceed_to_checkout',
        'payment_success',
        'start_time',
        'end_time',
        'created_at',
        'sync_status',
        'sync_attempts',
        'sync_claimed_at',
        'commerce_total',
        'commerce_subtotal',
        'commerce_shipping',
        'commerce_discount',
        'coupon_code',
        'discount_type',
        'line_count',
        'order_id',
        'amount_paid',
        'item_qty',
    ];

    protected function casts(): array
    {
        return [
            'add_to_cart' => 'array',
            'begin_checkout' => 'array',
            'proceed_to_checkout' => 'array',
            'payment_success' => 'array',
            'product_price' => 'decimal:2',
            'commerce_total' => 'decimal:2',
            'commerce_subtotal' => 'decimal:2',
            'commerce_shipping' => 'decimal:2',
            'commerce_discount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'created_at' => 'datetime',
            'sync_status' => 'integer',
            'sync_attempts' => 'integer',
            'sync_claimed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivityEcomUser::class, 'session_id', 'session_id');
    }
}
