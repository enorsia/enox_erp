<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class CommerceHasOrderFilter
{
    /**
     * @param  Builder<\App\Models\ActivityEcomUser>  $query
     */
    public static function apply(
        Builder $query,
        bool $hasOrder,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): void {
        if ($from !== null && $to !== null) {
            self::applyWithOrdersTable($query, $hasOrder, $from, $to);

            return;
        }

        if ($hasOrder) {
            $query->where('has_payment_success', true);
        } else {
            $query->where('has_payment_success', false);
        }
    }

    /**
     * Period-scoped order match from activity_ecom_orders (not first_payment_at).
     *
     * @param  Builder<\App\Models\ActivityEcomUser>  $query
     */
    public static function applyWithOrdersTable(
        Builder $query,
        bool $hasOrder,
        Carbon $from,
        Carbon $to,
    ): void {
        $table = $query->getModel()->getTable();
        $range = TrackerTime::storageRange($from, $to);

        $exists = function ($sub) use ($table, $range) {
            $sub->selectRaw('1')
                ->from('activity_ecom_orders as o')
                ->whereColumn('o.session_id', "{$table}.session_id")
                ->whereBetween('o.ordered_at', $range);
        };

        if ($hasOrder) {
            $query->whereExists($exists);
        } else {
            $query->whereNotExists($exists);
        }
    }
}
