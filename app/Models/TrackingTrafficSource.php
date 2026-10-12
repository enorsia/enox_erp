<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingTrafficSource extends Model
{
    protected $table = 'tracking_traffic_source';

    protected $fillable = [
        'name',
        'medium',
    ];
}
