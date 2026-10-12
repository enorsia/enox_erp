<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributionTouchLog extends Model
{
    protected $table = 'attribution_touch_log';

    protected $fillable = [
        'visitor_id',
        'session_id',
        'ingest_event_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'landing_page',
        'qualifies_paid',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'qualifies_paid' => 'boolean',
            'captured_at' => 'datetime',
        ];
    }
}
