<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingTrafficSource extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_traffic_source';

    protected $fillable = [
        'name',
        'medium',
    ];
}
