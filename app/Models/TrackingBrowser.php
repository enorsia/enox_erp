<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingBrowser extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_browser';

    protected $fillable = [
        'name',
    ];
}
