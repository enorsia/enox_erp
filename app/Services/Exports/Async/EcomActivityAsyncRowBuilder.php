<?php

namespace App\Services\Exports\Async;

use App\Models\ActivityEcomUser;
use App\Support\TrackerQueryParams;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EcomActivityAsyncRowBuilder
{
    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<int, string>
     */
    public static function headings(array $queryParams): array
    {
        return EcomActivityExportSchema::headings($queryParams);
    }

    /**
     * @param  Collection<int, ActivityEcomUser>  $sessions
     * @param  array<string, array<string, mixed>>  $rowMetrics
     * @param  array<string, mixed>  $queryParams
     * @return array{rows: array<int, array<int|string, mixed>>, merge_ranges: array<int, array{column: int, start_row: int, end_row: int}>}
     */
    public static function fromSessions(
        Collection $sessions,
        array $rowMetrics,
        array $queryParams,
        int &$serialStart = 1,
        int $dataRowStart = 0,
    ): array {
        $request = TrackerQueryParams::request($queryParams);
        $headings = self::headings($queryParams);
        $rows = [];
        $mergeRanges = [];
        $currentRow = $dataRowStart;

        foreach ($sessions as $session) {
            $metrics = $rowMetrics[$session->session_id] ?? [];
            $expanded = EcomActivityExportSchema::expandSession(
                $session,
                $metrics,
                $request,
                $serialStart,
            );

            $sessionRows = $expanded['rows'];
            $sessionRowCount = count($sessionRows);

            if ($sessionRowCount === 0) {
                continue;
            }

            $mergeRanges = EcomActivityExportSchema::mergeRangesForSessionBlock(
                $mergeRanges,
                $currentRow,
                $sessionRowCount,
                $expanded['event_row_counts'],
                $headings,
                $expanded['product_title_merges'] ?? [],
            );

            foreach ($sessionRows as $row) {
                $rows[] = $row;
            }

            $currentRow += $sessionRowCount;
        }

        return [
            'rows' => $rows,
            'merge_ranges' => $mergeRanges,
        ];
    }
}
