<?php

namespace App\Console\Commands;

use App\Support\TrackerDatabase;
use App\Support\TrackerRedisSupport;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PurgeTrackingDatabase extends Command
{
    protected $signature = 'tracker:empty-tracking
                            tracker:purge-tracking
                            {--dry-run : Show row counts per table without deleting}
                            {--force : Required to truncate data (ignored for dry-run)}
                            {--drop-tables : Drop tables instead of truncate (omit for a normal empty reset)}
                            {--queues : Also truncate jobs, failed_jobs, and job_batches on the tracking connection}
                            {--redis : Flush tracker Redis keys (prefix from TRACKER_REDIS_PREFIX)}';

    protected $description = 'Empty the tracking DB: TRUNCATE row data only (tables + migrations stay). Use --drop-tables only if you intend to re-migrate.';

    /**
     * Child tables first — matches database/migrations/tracking.
     *
     * @var list<string>
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

    /** @var list<string> */
    private const QUEUE_TABLES = [
        'failed_jobs',
        'jobs',
        'job_batches',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $dropTables = (bool) $this->option('drop-tables');

        if (! $dryRun && ! $this->option('force')) {
            $this->error('This permanently deletes all data on the tracking connection. Re-run with --force (or use --dry-run first).');

            return self::FAILURE;
        }

        $connectionName = TrackerDatabase::connectionName();
        $this->line('Tracking connection: '.$connectionName.' ('.TrackerDatabase::connection()->getDatabaseName().')');

        if ($dropTables && $dryRun) {
            $this->line('With --drop-tables, a live run would drop each table listed below.');
        }

        $tables = $this->tablesToProcess();

        $this->warn($dryRun
            ? 'Dry run: row counts only.'
            : ($dropTables ? 'Dropping tracking tables…' : 'Truncating tracking tables…'));

        $counts = $this->purgeTables($tables, $dryRun, $dropTables);
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

        if ($this->option('redis') && ! TrackerRedisSupport::usesMemoryBypass()) {
            $this->purgeRedis();
        }

        if ($dropTables) {
            $this->info('Tracking tables dropped. Run: php artisan migrate --database=tracking --path=database/migrations/tracking');
        } else {
            $this->info('Tracking database purge complete.');
            $this->line('Optional: php artisan tracker:sync-dashboard && php artisan tracker:sync-ctr');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function tablesToProcess(): array
    {
        $tables = self::TABLES;

        if ($this->option('queues')) {
            $tables = array_merge(self::QUEUE_TABLES, $tables);
        }

        return $tables;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function purgeTables(array $tables, bool $dryRun, bool $dropTables): array
    {
        $schema = $this->trackingSchema();
        $connection = TrackerDatabase::connection();
        $counts = [];

        foreach ($tables as $table) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            $counts[$table] = (int) $connection->table($table)->count();
        }

        if ($dryRun) {
            return $counts;
        }

        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach ($tables as $table) {
                if (! $schema->hasTable($table)) {
                    continue;
                }

                if ($dropTables) {
                    $schema->drop($table);

                    continue;
                }

                if ($driver === 'pgsql') {
                    $quoted = '"'.str_replace('"', '""', $table).'"';
                    $connection->statement('TRUNCATE TABLE '.$quoted.' RESTART IDENTITY CASCADE');
                } else {
                    $connection->table($table)->truncate();
                }
            }
        } finally {
            if ($driver === 'mysql') {
                $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        return $counts;
    }

    private function trackingSchema(): Builder
    {
        return Schema::connection(TrackerDatabase::connectionName());
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
