<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEcomUserAction extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'add_to_cart' => 'array',
            'begin_checkout' => 'array',
            'proceed_to_checkout' => 'array',
            'payment_success' => 'array',
            'product_price' => 'decimal:2',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivityEcomUser::class, 'session_id', 'session_id');
    }
}
