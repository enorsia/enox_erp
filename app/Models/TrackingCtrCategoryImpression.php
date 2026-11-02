<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;
use Illuminate\Database\Eloquent\Model;

class TrackingCtrCategoryImpression extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_ctr_categories_impression';

    public const TYPE_IMPRESSION = 0;

    public const TYPE_CLICK = 1;

    protected $fillable = [
        'tracking_session_id',
        'tracking_ctr_category_id',
        'micro_category_id',
        'micro_category_slug',
        'type',
        'product_code',
        'ctr_rollup_status',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'ctr_rollup_status' => 'boolean',
        ];
    }

    public function ctrCategory()
    {
        return $this->belongsTo(TrackingCtrCategory::class, 'tracking_ctr_category_id');
    }
}
