<?php

namespace App\Console\Commands;

use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TrackerTimestampsToUtc extends Command
{
    protected $signature = 'tracker:timestamps-to-utc {--dry-run : Show changes without writing}';

    protected $description = 'Backfill tracker timestamps from naive Europe/London values to UTC storage';

    /**
     * @var array<int, array{table: string, columns: array<int, string>}>
     */
    private array $targets = [
        ['table' => 'activity_ecom_user', 'columns' => ['created_at', 'updated_at', 'last_active_at']],
        ['table' => 'activity_ecom_user_actions', 'columns' => ['created_at', 'start_time', 'end_time']],
        ['table' => 'activity_ecom_daily_visitors', 'columns' => ['first_seen_at', 'last_seen_at', 'created_at', 'updated_at']],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $timezone = TrackerTime::timezone();
        $total = 0;

        foreach ($this->targets as $target) {
            $rows = DB::table($target['table'])->get();
            $this->info(sprintf('Scanning %s (%d rows)', $target['table'], $rows->count()));

            foreach ($rows as $row) {
                $updates = [];

                foreach ($target['columns'] as $column) {
                    $value = $row->{$column} ?? null;

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $utc = Carbon::parse($value, $timezone)->utc()->format('Y-m-d H:i:s');

                    if ($utc !== $value) {
                        $updates[$column] = $utc;
                    }
                }

                if ($updates === []) {
                    continue;
                }

                $total++;
                $this->line(sprintf(
                    '  %s id=%s: %s',
                    $target['table'],
                    $row->id ?? $row->session_id ?? '?',
                    json_encode($updates),
                ));

                if (! $dryRun) {
                    DB::table($target['table'])
                        ->where($this->primaryKey($target['table']), $this->primaryValue($row, $target['table']))
                        ->update($updates);
                }
            }
        }

        $this->info($dryRun
            ? "Dry run complete. {$total} row(s) would be updated."
            : "Backfill complete. {$total} row(s) updated.");

        return self::SUCCESS;
    }

    private function primaryKey(string $table): string
    {
        return match ($table) {
            'activity_ecom_user' => 'session_id',
            'activity_ecom_user_actions' => 'id',
            default => 'id',
        };
    }

    private function primaryValue(object $row, string $table): mixed
    {
        return match ($table) {
            'activity_ecom_user' => $row->session_id,
            default => $row->id,
        };
    }
}
