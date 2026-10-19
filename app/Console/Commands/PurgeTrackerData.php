<?php

namespace App\Console\Commands;

use App\Support\TrackerDatabase;
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
                            {--drop-tables : Drop tables (not just truncate) on the default ERP database connection}
                            {--queues : Also remove pending tracker jobs from ERP and tracking DB queue tables}
                            {--redis : Flush tracker Redis keys (prefix from TRACKER_REDIS_PREFIX)}';

    protected $description = 'Truncate legacy tracker tables on the default ERP DB only. Live ingest + tracking_* live on the tracking connection (not modified).';

    /**
     * ERP default connection only. Production ingest (activity_ecom_*) is on {@see TrackerDatabase}.
     *
     * @var list<string> Child tables first — same names as database/migrations/tracking
     */
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

        $this->warnTrackingConnectionIngestUntouched($dryRun);

        if ($dropTables && $dryRun) {
            $this->line('With --drop-tables, a live run would drop each table listed below.');
        }

        $this->warn($dryRun
            ? 'Dry run: row counts only.'
            : ($dropTables ? 'Dropping legacy tracker tables on the ERP database…' : 'Truncating legacy tracker tables on the ERP database…'));

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
            $this->info('Legacy tracker tables dropped on the ERP database. Live data is on the tracking connection (migrate:fresh there if needed).');
        } else {
            $this->info('ERP tracker table purge complete (tracking connection was not modified).');
        }

        return self::SUCCESS;
    }

    private function warnTrackingConnectionIngestUntouched(bool $dryRun): void
    {
        $schema = TrackerDatabase::connection()->getSchemaBuilder();

        if (! $schema->hasTable('activity_ecom_user')) {
            return;
        }

        $sessions = (int) TrackerDatabase::table('activity_ecom_user')->count();
        $actions = $schema->hasTable('activity_ecom_user_actions')
            ? (int) TrackerDatabase::table('activity_ecom_user_actions')->count()
            : 0;

        if ($sessions === 0 && $actions === 0) {
            return;
        }

        $this->warn(sprintf(
            '%s tracking DB (%s) still has ingest: %d session(s), %d action(s). This command does not touch that connection.',
            $dryRun ? '[dry-run note]' : 'Note:',
            TrackerDatabase::connectionName(),
            $sessions,
            $actions,
        ));
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
        $erp = $this->purgeTrackerQueueTables(DB::connection(), Schema::getConnection());
        $tracking = $this->purgeTrackerQueueTables(
            TrackerDatabase::connection(),
            TrackerDatabase::connection()->getSchemaBuilder(),
        );

        $this->line(sprintf(
            'Queue cleanup (ERP): jobs=%d, failed_jobs=%d, job_batches=%d',
            $erp['jobs'],
            $erp['failed'],
            $erp['batches'],
        ));

        $this->line(sprintf(
            'Queue cleanup (tracking): jobs=%d, failed_jobs=%d, job_batches=%d',
            $tracking['jobs'],
            $tracking['failed'],
            $tracking['batches'],
        ));
    }

    /**
     * @return array{jobs: int, failed: int, batches: int}
     */
    private function purgeTrackerQueueTables(\Illuminate\Database\Connection $connection, \Illuminate\Database\Schema\Builder $schema): array
    {
        $jobs = 0;
        $failed = 0;
        $batches = 0;

        if ($schema->hasTable('jobs')) {
            $jobs = $connection->table('jobs')
                ->where(function ($query): void {
                    $query->where('payload', 'like', '%TrackerDashboard%')
                        ->orWhere('payload', 'like', '%ProcessTrackingCtr%');
                })
                ->delete();
        }

        if ($schema->hasTable('failed_jobs')) {
            $failed = $connection->table('failed_jobs')
                ->where(function ($query): void {
                    $query->where('payload', 'like', '%TrackerDashboard%')
                        ->orWhere('payload', 'like', '%ProcessTrackingCtr%');
                })
                ->delete();
        }

        if ($schema->hasTable('job_batches')) {
            $batches = $connection->table('job_batches')
                ->where('name', 'tracker-dashboard-sync')
                ->delete();
        }

        return ['jobs' => $jobs, 'failed' => $failed, 'batches' => $batches];
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
