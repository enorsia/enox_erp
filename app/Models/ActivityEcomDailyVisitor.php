<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityEcomDailyVisitor extends Model
{
    protected $table = 'activity_ecom_daily_visitors';

    protected $fillable = [
        'visitor_id',
        'visit_date',
        'first_seen_at',
        'last_seen_at',
        'total_duration_seconds',
        'session_count',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'total_duration_seconds' => 'integer',
            'session_count' => 'integer',
        ];
    }
}
