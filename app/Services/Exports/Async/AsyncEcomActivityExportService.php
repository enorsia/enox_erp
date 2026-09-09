<?php

namespace App\Services\Exports\Async;

use App\Jobs\Exports\GenerateEcomActivityExportJob;
use App\Models\UserExport;
use App\Services\EcomActivityExportQuery;
use App\Support\ExportLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AsyncEcomActivityExportService
{
    public function __construct(
        private EcomActivityExportQuery $exportQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function estimateRowCount(array $filters): int
    {
        $queryParams = $filters['query'] ?? [];

        return $this->exportQuery->buildSortedQuery($queryParams)->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function start(int $userId, array $filters, string $format): UserExport
    {
        $lock = Cache::lock('export-start:'.$userId, 10);

        return $lock->block(10, function () use ($userId, $filters, $format) {
            return DB::transaction(function () use ($userId, $filters, $format) {
                $this->cancelExistingExports($userId, UserExport::TYPE_ECOM_ACTIVITY_REPORT);

                $export = UserExport::create([
                    'user_id' => $userId,
                    'type' => UserExport::TYPE_ECOM_ACTIVITY_REPORT,
                    'format' => $format,
                    'filters' => $filters,
                    'status' => UserExport::STATUS_QUEUED,
                    'progress' => 0,
                    'total_rows' => 0,
                    'processed_rows' => 0,
                    'notify_browser' => false,
                    'expires_at' => now()->addHours(config('exports.file_ttl_hours', 24)),
                ]);

                GenerateEcomActivityExportJob::dispatch($export->id)
                    ->onConnection(config('exports.queue_connection', env('QUEUE_CONNECTION', 'database')))
                    ->onQueue(config('exports.queue', 'default'));

                ExportLogger::info('Export queued', [
                    'export_id' => $export->id,
                    'user_id' => $userId,
                    'format' => $format,
                    'type' => UserExport::TYPE_ECOM_ACTIVITY_REPORT,
                ]);

                return $export;
            });
        });
    }

    public function cancel(UserExport $export): UserExport
    {
        $export->update([
            'status' => UserExport::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        $export->deleteFile();

        ExportLogger::info('Export cancelled in service', [
            'export_id' => $export->id,
            'user_id' => $export->user_id,
        ]);

        return $export->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function buildSortedQuery(array $filters)
    {
        return $this->exportQuery->buildSortedQuery($filters['query'] ?? []);
    }

    public function resolveFilePath(UserExport $export): string
    {
        $directory = 'exports/'.$export->user_id;
        Storage::makeDirectory($directory);

        return $directory.'/'.$export->id.'.'.$export->format;
    }

    public function createWriter(UserExport $export, string $absolutePath): Contracts\StreamingExportWriter
    {
        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filters = $export->filters ?? [];
        $queryParams = $filters['query'] ?? [];
        $headings = EcomActivityAsyncRowBuilder::headings($queryParams);
        $profile = SpreadsheetWriterProfile::ecomActivity(count($headings));

        return $export->format === 'csv'
            ? new OpenSpoutCsvWriter($absolutePath, $profile)
            : new OpenSpoutXlsxWriter($absolutePath, $profile);
    }

    private function cancelExistingExports(int $userId, string $type): void
    {
        $existing = UserExport::forUser($userId)
            ->ofType($type)
            ->whereIn('status', [UserExport::STATUS_QUEUED, UserExport::STATUS_PROCESSING, UserExport::STATUS_COMPLETED])
            ->get();

        foreach ($existing as $export) {
            $export->update([
                'status' => UserExport::STATUS_CANCELLED,
                'completed_at' => now(),
            ]);
            $export->deleteFile();
        }
    }
}
