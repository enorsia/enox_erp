<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingMainVisitor extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_main_visitor';

    protected $fillable = [
        'visitor_id',
        'name',
        'email',
        'phone',
    ];
}
