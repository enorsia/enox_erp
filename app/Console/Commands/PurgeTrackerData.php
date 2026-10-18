<?php

namespace App\Console\Commands;

use App\Support\TrackerRedisSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeTrackerData extends Command
{
    protected $signature = 'tracker:purge-data
                            {--dry-run : Show row counts per table without deleting}
                            {--force : Required to delete data (ignored for dry-run)}
                            {--drop-tables : Drop tables (not just truncate) on the default database connection}
                            {--queues : Also remove pending tracker jobs from jobs/failed_jobs/job_batches}
                            {--redis : Flush tracker Redis keys (prefix from TRACKER_REDIS_PREFIX)}';

    protected $description = 'Clear ecom tracker tables from the default DB (truncate, or --drop-tables to remove schema).';

    /** @var list<string> Child tables first — same names as database/migrations/tracking */
    private const TABLES = [
        'tracking_order_details',
        'tracking_order_addresses',
        'tracking_orders',
        'tracking_recoverable_sales',
        'tracking_session_p_cat',
        'tracking_session_details',
        'tracking_session',
        'tracking_daily_durations',
        'tracking_daily_audiences',
        'tracking_daily_products',
        'tracking_daily_categories',
        'tracking_daily_summaries',
        'tracking_hourly_summaries',
        'tracking_ctr_daily_summaries',
        'tracking_ctr_summaries',
        'activity_ecom_user_bot_context',
        'activity_ecom_user_actions',
        'activity_ecom_user',
        'tracking_daily_visitor',
        'tracking_main_visitor',
        'tracking_product',
        'tracking_category',
        'tracking_color',
        'tracking_size',
        'tracking_device',
        'tracking_browser',
        'tracking_traffic_source',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $dropTables = (bool) $this->option('drop-tables');

        if (! $dryRun && ! $this->option('force')) {
            $this->error('This permanently deletes all tracker data. Re-run with --force (or use --dry-run first).');

            return self::FAILURE;
        }

        if ($dropTables && $dryRun) {
            $this->line('With --drop-tables, a live run would drop each table listed below.');
        }

        $this->warn($dryRun
            ? 'Dry run: row counts only.'
            : ($dropTables ? 'Dropping tracker tables on the default database…' : 'Truncating tracker tables…'));

        $counts = $this->purgeTables($dryRun, $dropTables);
        $total = array_sum($counts);

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %s: %d', $table, $count));
        }

        $rowLabel = $dryRun ? 'that would be removed' : ($dropTables ? 'removed before drop' : 'removed');
        $this->info(sprintf('Tables: %d, rows %s: %d', count($counts), $rowLabel, $total));

        if ($dryRun) {
            return self::SUCCESS;
        }

        Cache::forget('tracker-dashboard-sync-start');

        if ($this->option('queues')) {
            $this->purgeQueues();
        }

        if ($this->option('redis') && ! TrackerRedisSupport::usesMemoryBypass()) {
            $this->purgeRedis();
        }

        if ($dropTables) {
            $this->info('Tracker tables dropped on the default database. Recreate on tracking DB: php artisan migrate --database=tracking --path=database/migrations/tracking');
        } else {
            $this->info('Tracker purge complete. Ingest and sync will repopulate from new storefront traffic.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function purgeTables(bool $dryRun, bool $dropTables): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = (int) DB::table($table)->count();
        }

        if ($dryRun) {
            return $counts;
        }

        $driver = Schema::getConnection()->getDriverName();

        // TRUNCATE/DROP on MySQL implicitly commits; do not wrap in DB::transaction().
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                if ($dropTables) {
                    Schema::drop($table);

                    continue;
                }

                if ($driver === 'pgsql') {
                    $quoted = '"'.str_replace('"', '""', $table).'"';
                    DB::statement('TRUNCATE TABLE '.$quoted.' RESTART IDENTITY CASCADE');
                } else {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        return $counts;
    }

    private function purgeQueues(): void
    {
        $jobs = 0;
        $failed = 0;
        $batches = 0;

        if (Schema::hasTable('jobs')) {
            $jobs = DB::table('jobs')
                ->where(function ($query): void {
                    $query->where('payload', 'like', '%TrackerDashboard%')
                        ->orWhere('payload', 'like', '%ProcessTrackingCtr%');
                })
                ->delete();
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')
                ->where(function ($query): void {
                    $query->where('payload', 'like', '%TrackerDashboard%')
                        ->orWhere('payload', 'like', '%ProcessTrackingCtr%');
                })
                ->delete();
        }

        if (Schema::hasTable('job_batches')) {
            $batches = DB::table('job_batches')
                ->where('name', 'tracker-dashboard-sync')
                ->delete();
        }

        $this->line(sprintf('Queue cleanup: jobs=%d, failed_jobs=%d, job_batches=%d', $jobs, $failed, $batches));
    }

    private function purgeRedis(): void
    {
        $connection = TrackerRedisSupport::connection();
        $prefix = (string) config('database.redis.'.TrackerRedisSupport::connectionName().'.options.prefix', '');
        $pattern = $prefix !== '' ? $prefix.'*' : '*';
        $keys = $connection->keys($pattern);
        $deleted = 0;

        if (is_array($keys) && $keys !== []) {
            foreach (array_chunk($keys, 500) as $chunk) {
                $deleted += (int) $connection->del(...$chunk);
            }
        }

        $this->line(sprintf('Tracker Redis: deleted %d key(s) matching %s', $deleted, $pattern));
    }
}
