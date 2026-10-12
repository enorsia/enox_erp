<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingMainVisitor extends Model
{
    protected $table = 'tracking_main_visitor';

    protected $fillable = [
        'visitor_id',
        'name',
        'email',
        'phone',
    ];
}
