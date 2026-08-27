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
            $query->where(function (Builder $inner) use ($hasOrder, $from, $to) {
                if ($hasOrder) {
                    $inner->where('has_payment_success', true)
                        ->whereBetween('first_payment_at', [$from, $to]);
                } else {
                    $inner->where('has_payment_success', false)
                        ->orWhereNull('first_payment_at')
                        ->orWhere(function (Builder $range) use ($from, $to) {
                            $range->where('has_payment_success', true)
                                ->where(function (Builder $outside) use ($from, $to) {
                                    $outside->where('first_payment_at', '<', $from)
                                        ->orWhere('first_payment_at', '>', $to);
                                });
                        });
                }
            });

            return;
        }

        if ($hasOrder) {
            $query->where('has_payment_success', true);
        } else {
            $query->where('has_payment_success', false);
        }
    }

    /**
     * Fallback when session flags are not yet backfilled.
     *
     * @param  Builder<\App\Models\ActivityEcomUser>  $query
     */
    public static function applyWithOrdersTable(
        Builder $query,
        bool $hasOrder,
        Carbon $from,
        Carbon $to,
    ): void {
        $exists = fn (Builder $sub) => $sub->selectRaw('1')
            ->from('activity_ecom_orders as o')
            ->whereColumn('o.session_id', 'activity_ecom_user.session_id')
            ->whereBetween('o.ordered_at', [$from, $to]);

        if ($hasOrder) {
            $query->whereExists($exists);
        } else {
            $query->whereNotExists($exists);
        }
    }
}
