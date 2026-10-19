<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingDailyVisitor extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_daily_visitor';

    protected $fillable = [
        'tracking_main_visitor_id',
        'daily_visitor_id',
        'visit_date',
    ];
}
