<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingSession extends Model
{
    use UsesTrackingDatabase;

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
        'has_add_to_cart',
        'has_begin_checkout',
        'has_proceed_checkout',
        'has_order',
    ];
}
