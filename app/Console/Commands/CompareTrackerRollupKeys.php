<?php

namespace App\Console\Commands;

use App\Services\TrackerRollupKeyCompareService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CompareTrackerRollupKeys extends Command
{
    protected $signature = 'tracker:compare-rollup-keys
                            {from? : Start metric date YYYY-MM-DD}
                            {to? : End metric date YYYY-MM-DD}
                            {--tolerance=0.01 : Numeric tolerance}';

    protected $description = 'Compare string-key vs catalog-ID totals on daily product/category rollups.';

    public function handle(TrackerRollupKeyCompareService $compare): int
    {
        $timezone = TrackerTime::timezone();
        $from = $this->argument('from')
            ? Carbon::parse((string) $this->argument('from'), $timezone)->toDateString()
            : TrackerTime::defaultRollupMetricDate();
        $to = $this->argument('to')
            ? Carbon::parse((string) $this->argument('to'), $timezone)->toDateString()
            : $from;

        $tolerance = max(0.0, (float) $this->option('tolerance'));
        $rows = $compare->compareRange($from, $to, $tolerance);

        if ($rows === []) {
            $this->warn('No catalog ID columns or no rows to compare.');

            return self::FAILURE;
        }

        $failures = 0;
        foreach ($rows as $row) {
            $status = $row['within_tolerance'] ? 'OK' : 'MISMATCH';
            if (! $row['within_tolerance']) {
                $failures++;
            }

            $this->line(sprintf(
                '%s %s %s string=%s id=%s delta=%s',
                $row['metric_date'],
                $row['scope'],
                $status,
                $row['string_total'],
                $row['id_total'],
                $row['delta'],
            ));
        }

        if ($failures > 0) {
            $this->error("{$failures} comparison(s) outside tolerance.");

            return self::FAILURE;
        }

        $this->info('All comparisons within tolerance.');

        return self::SUCCESS;
    }
}
