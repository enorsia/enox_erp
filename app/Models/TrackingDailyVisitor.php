<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingDailyVisitor extends Model
{
    protected $table = 'tracking_daily_visitor';

    protected $fillable = [
        'tracking_main_visitor_id',
        'daily_visitor_id',
        'visit_date',
    ];
}
