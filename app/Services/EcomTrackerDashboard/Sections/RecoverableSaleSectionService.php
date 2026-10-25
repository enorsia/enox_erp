<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Support\TrackerDatabase;
use App\Support\TrackerTime;

class RecoverableSaleSectionService
{
    private const RECOVERABLE_ROW_LIMIT = 20;

    /** latest_funnel_stage => panel; the stage is also the activity page funnel filter */
    private const RECOVERABLE_PANELS = [
        3 => ['panelId' => 'cart-abandon', 'title' => 'Cart abandoned', 'tone' => 'cart', 'emptyMessage' => 'No cart abandonment in this period.'],
        4 => ['panelId' => 'begin-checkout-abandon', 'title' => 'Begin checkout abandoned', 'tone' => 'begin', 'emptyMessage' => 'No begin checkout abandonment in this period.'],
        5 => ['panelId' => 'proceed-checkout-abandon', 'title' => 'Proceed checkout abandoned', 'tone' => 'proceed', 'emptyMessage' => 'No proceed checkout abandonment in this period.'],
        6 => ['panelId' => 'payment-success-events', 'title' => 'Payment success', 'tone' => 'success', 'emptyMessage' => 'No payment success events in this period.'],
    ];

    public function panels(string $from, string $to, string $backUrl): array
    {
        $ranked = TrackerDatabase::table('tracking_recoverable_sales as rs')
            ->join('tracking_session as ts', 'ts.id', '=', 'rs.tracking_session_id')
            ->whereBetween('rs.metric_date', [$from, $to])
            ->select('rs.type', 'rs.qty', 'rs.sale_value', 'rs.occurred_at', 'ts.session_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY rs.type ORDER BY rs.occurred_at DESC, rs.id DESC) as row_no')
            ->selectRaw('COUNT(*) OVER (PARTITION BY rs.type) as total_count')
            ->selectRaw('SUM(rs.sale_value) OVER (PARTITION BY rs.type) as total_value');

        $rows = TrackerDatabase::query()
            ->fromSub($ranked, 'r')
            ->where('row_no', '<=', self::RECOVERABLE_ROW_LIMIT)
            ->orderBy('row_no')
            ->get()
            ->groupBy('type');

        $panels = [];
        foreach (self::RECOVERABLE_PANELS as $stage => $panel) {
            $typeRows = $rows->get($stage, collect());

            $panels[] = $panel + [
                'stage' => $stage,
                'session_count' => (int) ($typeRows->first()->total_count ?? 0),
                'at_stake' => (float) ($typeRows->first()->total_value ?? 0),
                'rows' => $typeRows->map(fn (object $row) => [
                    'session_id' => (string) $row->session_id,
                    'session_label' => substr((string) $row->session_id, 0, 8).'…',
                    'qty' => (int) $row->qty,
                    'value' => (float) $row->sale_value,
                    'occurred_ago' => TrackerTime::diffForHumansFromStorage($row->occurred_at) ?? '—',
                    'activity_url' => route('admin.ecom-activity.show', ['session' => $row->session_id, 'back' => $backUrl]),
                ])->all(),
            ];
        }

        return $panels;
    }

}
