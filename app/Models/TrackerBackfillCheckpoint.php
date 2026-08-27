<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackerBackfillCheckpoint extends Model
{
    protected $table = 'tracker_backfill_checkpoints';

    protected $fillable = [
        'job_name',
        'chunk_key',
        'status',
        'records_processed',
        'last_action_id',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'records_processed' => 'integer',
            'last_action_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
