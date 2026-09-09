<?php

namespace App\Jobs;

use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RollupEcomAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $metricDate,
    ) {}

    public function handle(): void
    {
        $date = Carbon::parse($this->metricDate, TrackerTime::timezone())->startOfDay();
        [$from, $to] = TrackerTime::storageRange($date, $date->copy()->endOfDay());

        $sessionCount = (int) DB::table('activity_ecom_user')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $visitorCount = (int) DB::table('activity_ecom_user')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('visitor_id')
            ->distinct()
            ->count('visitor_id');

        $actionCount = (int) DB::table('activity_ecom_user_actions')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $funnelCounts = DB::table('activity_ecom_user_actions')
            ->select('action_type', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('action_type', ['add_to_cart', 'begin_checkout', 'proceed_checkout', 'payment_success'])
            ->groupBy('action_type')
            ->pluck('total', 'action_type');

        $orderStats = DB::table('activity_ecom_orders')
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(amount_paid), 0) as revenue_total, COALESCE(SUM(item_qty), 0) as items_sold_qty')
            ->whereBetween('ordered_at', [$from, $to])
            ->first();

        DB::table('activity_ecom_daily_site_metrics')->updateOrInsert(
            ['metric_date' => $date->toDateString()],
            [
                'session_count' => $sessionCount,
                'visitor_count' => $visitorCount,
                'action_count' => $actionCount,
                'add_to_cart_count' => (int) ($funnelCounts['add_to_cart'] ?? 0),
                'begin_checkout_count' => (int) ($funnelCounts['begin_checkout'] ?? 0),
                'proceed_checkout_count' => (int) ($funnelCounts['proceed_checkout'] ?? 0),
                'payment_success_count' => (int) ($funnelCounts['payment_success'] ?? 0),
                'order_count' => (int) ($orderStats->order_count ?? 0),
                'revenue_total' => (float) ($orderStats->revenue_total ?? 0),
                'items_sold_qty' => (float) ($orderStats->items_sold_qty ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
