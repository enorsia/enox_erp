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
        'ads_tax_payments',
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
            $currentMonthEnd = $now->copy()->endOfMonth()->toDateString();
            match ($range) {
                // Previous calendar month only
                'last_month' => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subMonth()->startOfMonth()->toDateString())
                    ->where('daily_ad_performances.month', '<=', $now->copy()->subMonth()->endOfMonth()->toDateString()),
                // Last 3 months up to and including current month (current + 2 previous = 3 total)
                'last_3_months' => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subMonths(2)->startOfMonth()->toDateString())
                    ->where('daily_ad_performances.month', '<=', $currentMonthEnd),
                // Last 6 months up to and including current month (current + 5 previous = 6 total)
                'last_6_months' => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subMonths(5)->startOfMonth()->toDateString())
                    ->where('daily_ad_performances.month', '<=', $currentMonthEnd),
                // Last 12 months up to and including current month (current + 11 previous = 12 total)
                'last_year' => $query
                    ->where('daily_ad_performances.month', '>=', $now->copy()->subMonths(11)->startOfMonth()->toDateString())
                    ->where('daily_ad_performances.month', '<=', $currentMonthEnd),
                default => null,
            };
        }

        // ── Custom date range ──────────────────────────────────────
        // Apply when range is empty OR explicitly set to 'custom'
        if (!$range || $range === 'custom') {
            if (!empty($filters['date_from'])) {
                $query->where('daily_ad_performances.month', '>=', Carbon::parse($filters['date_from'])->startOfMonth()->toDateString());
            }
            if (!empty($filters['date_to'])) {
                $query->where('daily_ad_performances.month', '<=', Carbon::parse($filters['date_to'])->endOfMonth()->toDateString());
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

