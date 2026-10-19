<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingSessionDetail extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_session_details';

    protected $fillable = [
        'tracking_session_id',
        'touch_sequence',
        'captured_at',
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
