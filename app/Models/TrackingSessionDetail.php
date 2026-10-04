<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSessionDetail extends Model
{
    protected $table = 'tracking_session_details';

    protected $fillable = [
        'tracking_session_id',
        'ip',
        'tracking_device_id',
        'tracking_browser_id',
        'tracking_traffic_source_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'landing_page',
        'referer',
        'user_agent',
    ];
}
