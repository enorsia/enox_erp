<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingCtrSummary extends Model
{
    protected $table = 'tracking_ctr_summaries';

    protected $fillable = [
        'department_id',
        'category_id',
        'product_id',
        'total_click',
        'total_impression',
        'ctr',
        'ctr_average',
        'new_in',
        'total_sold',
        'total_sold_rolling',
        'sold_out',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'category_id' => 'integer',
            'product_id' => 'integer',
            'new_in' => 'boolean',
            'total_click' => 'integer',
            'total_impression' => 'integer',
            'ctr' => 'decimal:6',
            'ctr_average' => 'decimal:6',
            'total_sold' => 'integer',
            'total_sold_rolling' => 'integer',
            'sold_out' => 'boolean',
        ];
    }
}
