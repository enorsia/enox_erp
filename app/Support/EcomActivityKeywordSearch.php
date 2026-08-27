<?php

namespace App\Support;

use App\Services\EcomTrackerDashboardService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EcomActivityKeywordSearch
{
    public static function apply(
        Builder $query,
        string $search,
        EcomTrackerDashboardService $dashboardService,
        Carbon $from,
        Carbon $to,
        ?string $period,
    ): void {
        $like = '%'.$search.'%';

        $query->where(function (Builder $keywordQuery) use ($search, $like, $dashboardService, $from, $to, $period) {
            $keywordQuery
                ->where('session_id', 'like', $like)
                ->orWhere('visitor_id', 'like', $like)
                ->orWhere('ip', 'like', $like)
                ->orWhere('user_name', 'like', $like)
                ->orWhere('user_email', 'like', $like)
                ->orWhere('user_phone', 'like', $like)
                ->orWhere('country', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('utm_source', 'like', $like)
                ->orWhere('utm_medium', 'like', $like)
                ->orWhere('utm_campaign', 'like', $like)
                ->orWhere('landing_page', 'like', $like)
                ->orWhereHas('botContext', fn (Builder $botContext) => $botContext
                    ->where('client_ip', 'like', $like)
                    ->orWhere('ip_country', 'like', $like)
                    ->orWhere('cf_ray', 'like', $like)
                    ->orWhere('bot_reason', 'like', $like))
                ->orWhereHas('actions', fn (Builder $actions) => $actions
                    ->where(function (Builder $actionQuery) use ($like) {
                        $actionQuery
                            ->where('page_url', 'like', $like)
                            ->orWhere('referer', 'like', $like)
                            ->orWhere('product_name', 'like', $like)
                            ->orWhere('product_code', 'like', $like)
                            ->orWhere('sku', 'like', $like)
                            ->orWhere('category_name', 'like', $like)
                            ->orWhere('category_code', 'like', $like)
                            ->orWhere('department_name', 'like', $like)
                            ->orWhere('general_color_name', 'like', $like)
                            ->orWhere('product_color_code', 'like', $like)
                            ->orWhere('coupon_code', 'like', $like);
                    }));

            self::applyCommerceKeywordMatches($keywordQuery, $search, $like);

            $catalogSessionIds = $dashboardService->productCatalogSessionIds(
                $from,
                $to,
                ['search' => $search],
                $period,
            );

            self::orWhereSessionIds($keywordQuery, $catalogSessionIds);
        });
    }

    /**
     * @param  Builder<\App\Models\ActivityEcomUser>  $query
     */
    private static function applyCommerceKeywordMatches(Builder $query, string $search, string $like): void
    {
        $query->orWhereExists(function ($orderQuery) use ($search, $like) {
            $orderQuery->selectRaw('1')
                ->from('activity_ecom_orders as o')
                ->whereColumn('o.session_id', 'activity_ecom_user.session_id')
                ->where(function ($inner) use ($search, $like) {
                    $inner->where('o.order_id', 'like', $like)
                        ->orWhere('o.customer_email', 'like', $like)
                        ->orWhere('o.customer_phone', 'like', $like)
                        ->orWhere('o.coupon_code', 'like', $like);
                });
        });

        $query->orWhereExists(function ($lineQuery) use ($search, $like) {
            $lineQuery->selectRaw('1')
                ->from('activity_ecom_commerce_line_items as li')
                ->whereColumn('li.session_id', 'activity_ecom_user.session_id')
                ->where(function ($inner) use ($search, $like) {
                    $inner->where('li.product_code', 'like', $like)
                        ->orWhere('li.sku', 'like', $like)
                        ->orWhere('li.department_name', 'like', $like)
                        ->orWhere('li.category_name', 'like', $like);

                    if (DB::connection()->getDriverName() === 'mysql' && strlen($search) >= 3) {
                        $inner->orWhereRaw('MATCH(li.product_name) AGAINST (? IN BOOLEAN MODE)', [$search.'*']);
                    } else {
                        $inner->orWhere('li.product_name', 'like', $like);
                    }
                });
        });
    }

    /**
     * @param  Builder<\App\Models\ActivityEcomUser>  $query
     * @param  Collection<int, string>  $sessionIds
     */
    private static function orWhereSessionIds(Builder $query, Collection $sessionIds): void
    {
        $ids = $sessionIds->values()->all();

        if ($ids === []) {
            return;
        }

        if (count($ids) <= 1000) {
            $query->orWhereIn('session_id', $ids);

            return;
        }

        $query->orWhere(function (Builder $inner) use ($ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $inner->orWhereIn('session_id', $chunk);
            }
        });
    }
}
