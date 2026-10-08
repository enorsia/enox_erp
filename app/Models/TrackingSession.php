<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSession extends Model
{
    protected $table = 'tracking_session';

    /** latest_funnel_stage code => label */
    public const FUNNEL_STAGES = [
        1 => 'Category view',
        2 => 'Product view',
        3 => 'Add to cart',
        4 => 'Begin checkout',
        5 => 'Proceed checkout',
        6 => 'Payment success',
    ];

    /** Stages offered in the activity filter (commerce stages only, no view stages) */
    public const FILTER_FUNNEL_STAGES = [3, 4, 5, 6];

    /** duration_seconds buckets; max is inclusive, null = no upper limit */
    public const DURATION_BUCKETS = [
        'under_1m' => ['label' => 'Under 1 min', 'min' => 0, 'max' => 59],
        '1_5m' => ['label' => '1–5 min', 'min' => 60, 'max' => 299],
        '5_15m' => ['label' => '5–15 min', 'min' => 300, 'max' => 899],
        '15_60m' => ['label' => '15–60 min', 'min' => 900, 'max' => 3599],
        'over_60m' => ['label' => 'Over 60 min', 'min' => 3600, 'max' => null],
    ];

    protected $fillable = [
        'tracking_daily_visitor_id',
        'session_id',
        'name',
        'email',
        'phone',
        'is_logged_in',
        'duration_seconds',
        'actions_count',
        'last_active_at',
        'latest_funnel_stage',
        'has_order',
    ];
}
