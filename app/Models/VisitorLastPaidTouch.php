<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorLastPaidTouch extends Model
{
    protected $table = 'visitor_last_paid_touch';

    protected $primaryKey = 'visitor_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'visitor_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'landing_page',
        'click_param',
        'captured_at',
        'source_kind',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }
}
