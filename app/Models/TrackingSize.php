<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingSize extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_size';

    protected $fillable = [
        'code',
        'name',
    ];
}
