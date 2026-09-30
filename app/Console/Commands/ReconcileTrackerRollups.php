<?php

namespace App\Console\Commands;

use App\Services\TrackerRollupReconcileService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReconcileTrackerRollups extends Command
{
    protected $signature = 'tracker:reconcile-rollups
                            {--from= : Start metric date YYYY-MM-DD (store timezone)}
                            {--to= : End metric date YYYY-MM-DD (default: same as --from)}
                            {--days= : Last N closed days ending yesterday (overrides --from/--to)}
                            {--tolerance=0.01 : Max allowed |rollup revenue − orders revenue| per day}';

    protected $description = 'Read-only: compare daily site rollup revenue/order count to activity_ecom_orders on ordered_at (store TZ).';

    public function handle(TrackerRollupReconcileService $reconcile): int
    {
        $timezone = TrackerTime::timezone();
        $tolerance = max(0.0, (float) $this->option('tolerance'));

        [$fromDate, $toDate] = $this->resolveDateRange($timezone);

        $this->line(sprintf(
            'Reconcile metric dates %s → %s (%s). Orders grain: ordered_at. Read-only.',
            $fromDate,
            $toDate,
            $timezone,
        ));
        $this->newLine();

        $rows = $reconcile->compareRange($fromDate, $toDate, $tolerance);
        $failures = 0;
        $missingRollups = 0;

        foreach ($rows as $row) {
            if ($row['rollup_row_missing']) {
                $missingRollups++;
            }

            if (! $row['within_tolerance']) {
                $failures++;
            }

            $this->printDayRow($row, $tolerance);
        }

        $this->newLine();

        if ($missingRollups > 0) {
            $this->warn(sprintf('%d day(s) have no activity_ecom_daily_site_metrics row.', $missingRollups));
        }

        if ($failures > 0) {
            $this->error(sprintf('%d day(s) outside tolerance %.2f.', $failures, $tolerance));
            $this->line('If rollup_source_* differs from orders_* but rollup matches source, rollups follow CommerceFunnelQuery (orders + action fallback).');
            $this->line('If rollup differs from orders, check ordered_at vs rollup window (TrackerTime::localCalendarDateStorageRange).');

            return self::FAILURE;
        }

        $this->info('All compared days match orders on ordered_at.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(string $timezone): array
    {
        $days = $this->option('days');
        if ($days !== null && $days !== '') {
            $count = max(1, (int) $days);
            $yesterday = Carbon::now($timezone)->subDay()->startOfDay();
            $from = $yesterday->copy()->subDays($count - 1);

            return [$from->toDateString(), $yesterday->toDateString()];
        }

        $fromOpt = $this->option('from');
        if ($fromOpt === null || $fromOpt === '') {
            $fromDate = TrackerTime::defaultRollupMetricDate();
            $toDate = $fromDate;

            return [$fromDate, $toDate];
        }

        $fromDate = Carbon::parse((string) $fromOpt, $timezone)->toDateString();
        $toOpt = $this->option('to');
        $toDate = ($toOpt === null || $toOpt === '')
            ? $fromDate
            : Carbon::parse((string) $toOpt, $timezone)->toDateString();

        return [$fromDate, $toDate];
    }

    /**
     * @param  array{
     *     metric_date: string,
     *     rollup_revenue: float|null,
     *     rollup_order_count: int|null,
     *     orders_revenue: float,
     *     orders_count: int,
     *     rollup_source_revenue: float,
     *     rollup_source_purchases: int,
     *     revenue_delta: float|null,
     *     order_count_delta: int|null,
     *     within_tolerance: bool,
     *     rollup_row_missing: bool,
     *     orders_by_currency: array<string, array{revenue: float, count: int}>
     * }  $row
     */
    private function printDayRow(array $row, float $tolerance): void
    {
        if ($row['rollup_row_missing']) {
            $this->line($row['metric_date'].'  NO ROLLUP');
        } elseif ($row['within_tolerance']) {
            $this->line($row['metric_date'].'  OK');
        } else {
            $this->line($row['metric_date'].'  MISMATCH');
        }

        if ($row['rollup_row_missing']) {
            $this->line(sprintf(
                '  orders: revenue %s, count %d',
                number_format($row['orders_revenue'], 2),
                $row['orders_count'],
            ));
        } else {
            $this->line(sprintf(
                '  rollup: revenue %s, orders %d  |  orders (ordered_at): revenue %s, count %d  |  Δ revenue %s, Δ count %s',
                number_format((float) $row['rollup_revenue'], 2),
                $row['rollup_order_count'],
                number_format($row['orders_revenue'], 2),
                $row['orders_count'],
                $row['revenue_delta'] === null ? 'n/a' : number_format($row['revenue_delta'], 2),
                $row['order_count_delta'] === null ? 'n/a' : (string) $row['order_count_delta'],
            ));
        }

        $this->line(sprintf(
            '  rollup job source (paymentMetricTotals): revenue %s, purchases %d',
            number_format($row['rollup_source_revenue'], 2),
            $row['rollup_source_purchases'],
        ));

        foreach ($row['orders_by_currency'] as $currency => $totals) {
            $rollupCurrency = $row['rollup_by_currency'][$currency] ?? ['revenue' => 0.0, 'count' => 0];
            $this->line(sprintf(
                '  currency [%s]: orders revenue %s count %d  |  rollup dim revenue %s count %d',
                $currency,
                number_format($totals['revenue'], 2),
                $totals['count'],
                number_format($rollupCurrency['revenue'], 2),
                $rollupCurrency['count'],
            ));
        }

        if ($row['currency_mismatches'] !== []) {
            $this->line('  currency mismatches: '.implode(', ', $row['currency_mismatches']));
        }

        $this->line(sprintf(
            '  data quality: bot-flagged sessions with paid orders (ordered_at window): %d',
            $row['bot_sessions_with_paid_orders'],
        ));

        if (! $row['within_tolerance'] && ! $row['rollup_row_missing']) {
            $this->line(sprintf('  tolerance: %.2f', $tolerance));
        }

        $this->newLine();
    }
}
