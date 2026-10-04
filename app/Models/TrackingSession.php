<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSession extends Model
{
    protected $table = 'tracking_session';

    protected $fillable = [
        'tracking_daily_visitor_id',
        'session_id',
        'name',
        'email',
        'phone',
        'is_logged_in',
        'duration_seconds',
        'last_active_at',
        'latest_funnel_stage',
        'has_order',
    ];
}
