<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingBrowser extends Model
{
    protected $table = 'tracking_browser';

    protected $fillable = [
        'name',
    ];
}
