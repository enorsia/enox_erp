<?php

namespace App\Services;

use App\Models\ActivityEcomOrder;
use App\Models\ActivityEcomUserAction;
use App\Support\CommercePricingExtractor;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CommerceSyncValidator
{
    /**
     * @return list<string>
     */
    public function validateChunk(Carbon $from, Carbon $to): array
    {
        $issues = [];

        $orders = DB::table('activity_ecom_orders')
            ->whereBetween('ordered_at', [
                TrackerTime::formatUtc($from),
                TrackerTime::formatUtc($to),
            ])
            ->get();

        foreach ($orders as $order) {
            $lineSum = (float) DB::table('activity_ecom_commerce_line_items')
                ->where('order_id', $order->order_id)
                ->where('funnel_stage', 'payment_success')
                ->sum('line_total');

            if (abs((float) $order->amount_paid - $lineSum) > 0.01) {
                $issues[] = "order {$order->order_id}: amount_paid {$order->amount_paid} vs lines {$lineSum}";
            }
        }

        $missingOrders = ActivityEcomUserAction::query()
            ->where('action_type', 'payment_success')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->filter(function (ActivityEcomUserAction $action) {
                $orderId = CommercePricingExtractor::orderId(is_array($action->payment_success) ? $action->payment_success : []);
                if ($orderId === null) {
                    return false;
                }

                return ! ActivityEcomOrder::query()->where('order_id', $orderId)->exists();
            });

        foreach ($missingOrders as $action) {
            $issues[] = "missing order row for action {$action->id} event {$action->event_id}";
        }

        if ($issues !== []) {
            EcomTrackerLogger::frontend()->warning('commerce.sync.validate.mismatch', 'Commerce validation mismatches', [
                'count' => count($issues),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
        }

        return $issues;
    }

    /**
     * @param  list<string>  $issues
     */
    public function writeReport(array $issues, Carbon $date): void
    {
        if ($issues === []) {
            return;
        }

        $path = storage_path('logs/commerce-sync-'.$date->format('Y-m-d').'.log');
        File::append($path, implode(PHP_EOL, $issues).PHP_EOL);
    }
}
