<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingRecoverableSale extends Model
{
    /** type code (same as TrackingSession::FUNNEL_STAGES) => panel title */
    public const TYPES = [
        3 => 'Cart abandoned',
        4 => 'Begin checkout abandoned',
        5 => 'Proceed checkout abandoned',
        6 => 'Payment success',
    ];

    protected $fillable = [
        'metric_date',
        'type',
        'tracking_session_id',
        'qty',
        'sale_value',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'type' => 'integer',
            'tracking_session_id' => 'integer',
            'qty' => 'integer',
            'sale_value' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id');
    }
}
