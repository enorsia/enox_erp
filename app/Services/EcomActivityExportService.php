<?php

namespace App\Services;

use App\Services\Exports\Async\OpenSpoutXlsxWriter;
use App\Services\Exports\Async\SpreadsheetWriterProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcomActivityExportService
{
    /** @var list<string> */
    private const HEADINGS = [
        'SL',
        'Action',
        'Name',
        'Email',
        'Phone',
        'Visitor type',
        'Product code',
        'SKU',
        'Title',
        'Color',
        'Size',
        'Qty',
        'Amount',
        'Order ID',
        'Source',
        'Medium',
        'Device',
        'Session start time',
        'Action occur time',
    ];

    /**
     * @param  list<list<int|float|string|null>>  $rows
     * @param  list<array{column: int, start_row: int, end_row: int}>  $merges
     * @param  array<int, list<int>>  $separators
     */
    public function download(
        array $rows,
        Carbon $from,
        Carbon $to,
        string $filterSummary,
        array $merges = [],
        array $separators = [],
    ): BinaryFileResponse {
        $headings = self::HEADINGS;
        $profile = SpreadsheetWriterProfile::ecomActivity($headings);

        $tempPath = storage_path('app/temp/ecom-activity-'.uniqid('', true).'.xlsx');
        File::ensureDirectoryExists(dirname($tempPath));

        $writer = new OpenSpoutXlsxWriter($tempPath, $profile);
        $writer->writeHeader($headings, [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
            'filter_summary' => $filterSummary,
        ]);
        $writer->writeRows($rows, [
            'merge_ranges' => $merges,
            'separator_columns' => $separators,
        ]);
        $writer->close();

        $filename = 'User activity '.$from->format('Y-m-d').' to '.$to->format('Y-m-d').'.xlsx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
