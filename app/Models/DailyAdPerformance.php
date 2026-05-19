<?php

namespace App\Models;

use Carbon\Carbon;

class DailyAdPerformance extends BaseModel
{
    protected $table = 'daily_ad_performances';

    protected $fillable = [
        'sale_platform_id',
        'month',
        'reach',
        'impressions',
        'clicks',
        'sessions',
        'engaged_sessions',
        'users',
        'net_cost',
        'ads_tax_payments',
        'total_cost',
        'number_of_orders',
        'number_of_products',
        'sales_grow_percent',
        'revenue',
        'total_revenue',
        'total_return',
        'net_revenue',
        'roi',
        'roas',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
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
            $platformId    = (int) $filters['sale_platform_id'];
            $childIds      = SalePlatform::where('parent_id', $platformId)->pluck('id')->toArray();
            $grandChildIds = empty($childIds)
                ? []
                : SalePlatform::whereIn('parent_id', $childIds)->pluck('id')->toArray();
            $allIds = array_unique(array_merge([$platformId], $childIds, $grandChildIds));
            $query->whereIn('daily_ad_performances.sale_platform_id', $allIds);
        }

        // ── Preset date range ──────────────────────────────────────
        $range = $filters['date_range'] ?? null;
        if ($range && $range !== 'custom') {
            $now = Carbon::now();
            match ($range) {
                'last_month'   => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subMonth()->startOfMonth()->toDateString())
                    ->where('daily_ad_performances.month', '<=', $now->copy()->subMonth()->endOfMonth()->toDateString()),
                'last_3_months' => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subMonths(3)->startOfMonth()->toDateString()),
                'last_6_months' => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subMonths(6)->startOfMonth()->toDateString()),
                'last_year'    => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subYear()->startOfMonth()->toDateString()),
                default        => null,
            };
        }

        // ── Custom date range ──────────────────────────────────────
        if ((!$range || $range === 'custom')) {
            if (!empty($filters['date_from'])) {
                $query->where('daily_ad_performances.month', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->where('daily_ad_performances.month', '<=', $filters['date_to']);
            }
        }

        if (!empty($filters['year'])) {
            $query->whereYear('daily_ad_performances.month', (int) $filters['year']);
        }

        if (!empty($filters['month_num'])) {
            $query->whereMonth('daily_ad_performances.month', (int) $filters['month_num']);
        }
    }
}

