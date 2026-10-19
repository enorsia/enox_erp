<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingCtrDailySummary extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_ctr_daily_summaries';

    protected $fillable = [
        'metric_date',
        'department_id',
        'category_id',
        'product_code',
        'total_click',
        'total_impression',
        'ctr',
        'ctr_average',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'department_id' => 'integer',
            'category_id' => 'integer',
            'product_code' => 'string',
            'total_click' => 'integer',
            'total_impression' => 'integer',
            'ctr' => 'decimal:6',
            'ctr_average' => 'decimal:6',
        ];
    }
}
