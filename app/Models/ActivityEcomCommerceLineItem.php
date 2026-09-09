<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEcomCommerceLineItem extends Model
{
    public $timestamps = false;

    protected $table = 'activity_ecom_commerce_line_items';

    protected $fillable = [
        'event_id',
        'session_id',
        'visitor_id',
        'funnel_stage',
        'order_id',
        'line_no',
        'product_id',
        'product_code',
        'sku',
        'product_name',
        'department_name',
        'category_name',
        'category_code',
        'color_name',
        'size_name',
        'qty',
        'unit_price',
        'line_total',
        'currency',
        'product_snapshot_json',
        'staged_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'product_snapshot_json' => 'array',
            'staged_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivityEcomUser::class, 'session_id', 'session_id');
    }
}
