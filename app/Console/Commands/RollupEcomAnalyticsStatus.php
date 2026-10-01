<?php

namespace App\Console\Commands;

use App\Support\EcomDailyRollupDayStatus;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RollupEcomAnalyticsStatus extends Command
{
    protected $signature = 'tracker:rollup-analytics-status
                            {--failed : List only failed days}
                            {--limit=30 : Max rows to list}';

    protected $description = 'Show daily rollup success / failed / pending flags (for retry and ops).';

    public function handle(): int
    {
        if (! EcomDailyRollupDayStatus::hasTable()) {
            $this->error('Run migrations first (activity_ecom_rollup_day_status).');

            return self::FAILURE;
        }

        $timezone = TrackerTime::timezone();
        $today = Carbon::now($timezone)->toDateString();

        $counts = DB::table(EcomDailyRollupDayStatus::table())
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $this->info('Rollup day status ('.$timezone.', today='.$today.')');
        $this->line('  success: '.(int) ($counts[EcomDailyRollupDayStatus::SUCCESS] ?? 0));
        $this->line('  failed:  '.(int) ($counts[EcomDailyRollupDayStatus::FAILED] ?? 0));
        $this->line('  pending: '.(int) ($counts[EcomDailyRollupDayStatus::PENDING] ?? 0));

        $query = DB::table(EcomDailyRollupDayStatus::table())->orderByDesc('metric_date');

        if ($this->option('failed')) {
            $query->where('status', EcomDailyRollupDayStatus::FAILED);
        }

        $rows = $query->limit((int) $this->option('limit'))->get();

        if ($rows->isEmpty()) {
            $this->line('No status rows to list.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['date', 'status', 'rolled_up_at', 'last_error'],
            $rows->map(fn ($row) => [
                (string) $row->metric_date,
                (string) $row->status,
                $row->rolled_up_at ?? '—',
                $row->last_error ? mb_substr((string) $row->last_error, 0, 80) : '—',
            ])->all(),
        );

        $this->line('Retry failed/missing: php artisan tracker:rollup-analytics-backfill');

        return self::SUCCESS;
    }
}
