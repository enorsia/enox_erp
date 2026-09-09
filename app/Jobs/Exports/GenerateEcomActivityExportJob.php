<?php

namespace App\Jobs\Exports;

use App\Jobs\Exports\Concerns\HandlesExportFailures;
use App\Models\UserExport;
use App\Services\EcomActivityExportQuery;
use App\Services\EcomActivityRowMetrics;
use App\Services\Exports\Async\AsyncEcomActivityExportService;
use App\Services\Exports\Async\EcomActivityAsyncRowBuilder;
use App\Support\EcomActivityFocus;
use App\Support\ExportLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateEcomActivityExportJob implements ShouldQueue
{
    use Dispatchable, HandlesExportFailures, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public int $exportId)
    {
        $this->onConnection(config('exports.queue_connection', env('QUEUE_CONNECTION', 'database')));
        $this->onQueue(config('exports.queue', 'default'));
    }

    public function handle(
        AsyncEcomActivityExportService $service,
        EcomActivityExportQuery $exportQuery,
        EcomActivityRowMetrics $rowMetrics,
    ): void {
        $export = UserExport::find($this->exportId);

        if (! $export) {
            ExportLogger::warning('Job skipped — export not found', ['export_id' => $this->exportId]);

            return;
        }

        if ($export->status === UserExport::STATUS_CANCELLED) {
            ExportLogger::info('Job skipped — export cancelled', ['export_id' => $export->id]);

            return;
        }

        $filters = $export->filters ?? [];
        $queryParams = $filters['query'] ?? [];
        $range = $exportQuery->resolveRange($queryParams);
        $focus = $queryParams['focus'] ?? null;

        if ($export->total_rows <= 0) {
            $totalRows = $service->estimateRowCount($filters);
            $export->update(['total_rows' => $totalRows]);

            ExportLogger::info('Row estimate completed', [
                'export_id' => $export->id,
                'total_rows' => $totalRows,
            ]);
        }

        $export->update([
            'status' => UserExport::STATUS_PROCESSING,
            'started_at' => now(),
            'progress' => 0,
        ]);

        $relativePath = $service->resolveFilePath($export);
        $absolutePath = Storage::path($relativePath);

        ExportLogger::info('Writing file', [
            'export_id' => $export->id,
            'path' => $relativePath,
        ]);

        $writer = $service->createWriter($export, $absolutePath);
        $headerContext = [
            'start_date' => $range['from']->toDateString(),
            'end_date' => $range['to']->toDateString(),
            'range_label' => $filters['range_label'] ?? $range['label'],
        ];
        $writer->writeHeader(EcomActivityAsyncRowBuilder::headings($queryParams), $headerContext);

        $funnelMetrics = $exportQuery->funnelMetricsForExport($queryParams);
        $catalogOptions = $exportQuery->productCatalogOptions($queryParams);
        $serialStart = 1;
        $chunkCount = 0;
        $lastProgressUpdate = microtime(true);
        $progressUpdateInterval = (float) config('exports.progress_update_interval_seconds', 1);
        $processedRows = 0;
        $pendingProgressUpdate = false;
        $chunkSize = max(25, (int) config('exports.report_chunk_size', 250));
        $batch = collect();

        $flushProgress = function () use ($export, &$processedRows, &$lastProgressUpdate, &$pendingProgressUpdate): void {
            $progress = $export->total_rows > 0
                ? min(99, (int) round(($processedRows / $export->total_rows) * 100))
                : 0;

            $export->update([
                'processed_rows' => $processedRows,
                'progress' => $progress,
            ]);

            $lastProgressUpdate = microtime(true);
            $pendingProgressUpdate = false;
        };

        try {
            $query = $service->buildSortedQuery($filters);

            foreach ($query->cursor() as $session) {
                if (UserExport::whereKey($export->id)->value('status') === UserExport::STATUS_CANCELLED) {
                    ExportLogger::info('Job stopped — cancelled mid-run', ['export_id' => $export->id]);

                    break;
                }

                $batch->push($session);

                if ($batch->count() < $chunkSize) {
                    continue;
                }

                $processedRows += $this->writeBatch(
                    $writer,
                    $batch,
                    $rowMetrics,
                    $focus,
                    $range,
                    $funnelMetrics,
                    $catalogOptions,
                    $queryParams,
                    $serialStart,
                );

                $batch = collect();
                $chunkCount++;
                $pendingProgressUpdate = true;

                $now = microtime(true);
                if ($chunkCount === 1 || ($now - $lastProgressUpdate) >= $progressUpdateInterval) {
                    $flushProgress();
                }

                gc_collect_cycles();
            }

            if ($batch->isNotEmpty() && UserExport::whereKey($export->id)->value('status') !== UserExport::STATUS_CANCELLED) {
                $processedRows += $this->writeBatch(
                    $writer,
                    $batch,
                    $rowMetrics,
                    $focus,
                    $range,
                    $funnelMetrics,
                    $catalogOptions,
                    $queryParams,
                    $serialStart,
                );
                $pendingProgressUpdate = true;
            }

            if ($pendingProgressUpdate && UserExport::whereKey($export->id)->value('status') !== UserExport::STATUS_CANCELLED) {
                $flushProgress();
            }

            if (UserExport::whereKey($export->id)->value('status') === UserExport::STATUS_CANCELLED) {
                $writer->close();
                Storage::delete($relativePath);

                return;
            }

            $sheetCount = $export->format === 'xlsx' ? $writer->getSheetCount() : null;
            $writer->close();
            unset($writer);

            $fileSize = Storage::exists($relativePath) ? Storage::size($relativePath) : 0;

            $export->update([
                'status' => UserExport::STATUS_COMPLETED,
                'progress' => 100,
                'processed_rows' => $processedRows,
                'file_path' => $relativePath,
                'file_size' => $fileSize,
                'sheet_count' => $sheetCount,
                'completed_at' => now(),
            ]);

            ExportLogger::info('Job completed', [
                'export_id' => $export->id,
                'rows' => $processedRows,
                'file_size' => $fileSize,
                'sheets' => $sheetCount,
            ]);
        } catch (Throwable $e) {
            ExportLogger::error('Job failed', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($writer)) {
                try {
                    $writer->close();
                } catch (Throwable) {
                }
            }

            Storage::delete($relativePath);
            $this->notifyExportFailure($export, $e);

            throw $e;
        }
    }

    /**
     * @param  Collection<int, \App\Models\ActivityEcomUser>  $batch
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @param  array<string, mixed>  $catalogOptions
     * @param  array<string, mixed>  $queryParams
     */
    private function writeBatch(
        $writer,
        Collection $batch,
        EcomActivityRowMetrics $rowMetrics,
        ?string $focus,
        array $range,
        array $funnelMetrics,
        array $catalogOptions,
        array $queryParams,
        int &$serialStart,
    ): int {
        $metrics = $rowMetrics->forSessions(
            $batch,
            EcomActivityFocus::isValid($focus) ? $focus : null,
            $range['from'],
            $range['to'],
            $funnelMetrics,
            $catalogOptions,
        );

        $rows = EcomActivityAsyncRowBuilder::fromSessions(
            $batch,
            $metrics,
            $queryParams,
            $serialStart,
        );

        $writer->writeRows($rows);

        foreach ($batch as $session) {
            $session->unsetRelations();
        }

        unset($metrics, $rows);

        return $batch->count();
    }

    public function failed(Throwable $e): void
    {
        $export = UserExport::find($this->exportId);

        if (! $export) {
            return;
        }

        if ($export->status !== UserExport::STATUS_FAILED) {
            $this->persistPermanentExportFailure($export, $e);
        }
    }
}
