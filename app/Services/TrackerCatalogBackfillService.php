<?php

namespace App\Services;

use App\Support\TrackerCatalogSentinels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrackerCatalogBackfillService
{
    public function __construct(
        private TrackerCatalogResolver $resolver,
    ) {}

    public function stampLineItems(?int $limit = null): int
    {
        if (! Schema::hasColumn('activity_ecom_commerce_line_items', 'tracker_product_id')) {
            return 0;
        }

        $updated = 0;
        $query = DB::table('activity_ecom_commerce_line_items')
            ->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $row) {
            $line = (array) $row;
            $ids = $this->resolver->resolveLineSnapshotIds($line);

            DB::table('activity_ecom_commerce_line_items')
                ->where('id', $row->id)
                ->update([
                    'tracker_department_id' => $ids['tracker_department_id'],
                    'tracker_category_id' => $ids['tracker_category_id'],
                    'tracker_product_id' => $ids['tracker_product_id'],
                ]);

            $updated++;
        }

        return $updated;
    }

    public function stampMissingLineItems(int $chunkSize = 500): int
    {
        if (! Schema::hasColumn('activity_ecom_commerce_line_items', 'tracker_product_id')) {
            return 0;
        }

        $updated = 0;

        DB::table('activity_ecom_commerce_line_items')
            ->where(function ($query) {
                $query->whereNull('tracker_product_id')
                    ->orWhereNull('tracker_category_id')
                    ->orWhereNull('tracker_department_id');
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updated) {
                foreach ($rows as $row) {
                    $ids = $this->resolver->resolveLineSnapshotIds((array) $row);
                    DB::table('activity_ecom_commerce_line_items')
                        ->where('id', $row->id)
                        ->update($ids);
                    $updated++;
                }
            });

        return $updated;
    }

}
