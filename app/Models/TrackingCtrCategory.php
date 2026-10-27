<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;
use Illuminate\Database\Eloquent\Model;

class TrackingCtrCategory extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_ctr_categories';

    public const TYPE_STATIC = 'static';

    public const TYPE_DYNAMIC_CUSTOM_LINK = 'dynamic_custom_link';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_MICRO_CATEGORY = 'micro_category';

    protected $fillable = [
        'department_id',
        'type',
        'category_id',
        'category_slug',
    ];
}
