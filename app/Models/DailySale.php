<?php

namespace App\Models;

use Carbon\Carbon;

class DailySale extends BaseModel
{
    protected $fillable = [
        'sale_platform_id',
        'date',
        'spent',
        'sales',
        'number_of_orders',
        'number_of_quantities',
        'number_of_male_orders',
        'number_of_female_orders',
        'number_of_kids_orders',
        'number_of_male_quantities',
        'number_of_female_quantities',
        'number_of_kids_quantities',
    ];

    protected function casts(): array
    {
        return [
            'date'  => 'date',
        ];
    }

    public function getCreatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function getUpdatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    // ── Relationships ─────────────────────────────────────────────

    public function salePlatform()
    {
        return $this->belongsTo(SalePlatform::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['sale_platform_id'])) {
            // Expand to include all child + grandchild platform IDs so parent platform
            // filter returns records belonging to any of its descendants
            $platformId    = (int) $filters['sale_platform_id'];
            $childIds      = SalePlatform::where('parent_id', $platformId)->pluck('id')->toArray();
            $grandChildIds = empty($childIds)
                ? []
                : SalePlatform::whereIn('parent_id', $childIds)->pluck('id')->toArray();
            $allIds = array_unique(array_merge([$platformId], $childIds, $grandChildIds));
            $query->whereIn('daily_sales.sale_platform_id', $allIds);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('daily_sales.date', (int) $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->whereMonth('daily_sales.date', (int) $filters['month']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate('daily_sales.date', $filters['date']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('daily_sales.date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('daily_sales.date', '<=', $filters['date_to']);
        }
    }
}

