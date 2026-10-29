<?php

namespace App\Services;

use App\Services\Exports\Async\OpenSpoutXlsxWriter;
use App\Services\Exports\Async\SpreadsheetWriterProfile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcommerceCtrSummaryExportService
{
    /** @var list<string> */
    private const HEADINGS = [
        'Department',
        'Category',
        'Micro category',
        'Product code',
        'CTR',
        'CTR average',
        'Click',
        'Impression',
        '7 days sold',
        'Total sold',
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function download(array $rows, string $filterSummary): BinaryFileResponse
    {
        $headings = self::HEADINGS;
        $profile = SpreadsheetWriterProfile::ecomCtrSummary($headings);

        $tempPath = storage_path('app/temp/ecom-ctr-summary-'.uniqid('', true).'.xlsx');
        File::ensureDirectoryExists(dirname($tempPath));

        $writer = new OpenSpoutXlsxWriter($tempPath, $profile);
        $writer->writeHeader($headings, [
            'filter_summary' => $filterSummary,
        ]);

        $spreadsheetRows = [];
        foreach ($rows as $row) {
            $spreadsheetRows[] = [
                (string) ($row['department'] ?? ''),
                (string) ($row['category'] ?? ''),
                (string) ($row['micro_category'] ?? '—'),
                (string) ($row['product_code'] ?? ''),
                round((float) ($row['ctr'] ?? 0) * 100, 2),
                round((float) ($row['ctr_average'] ?? 0) * 100, 2),
                (int) ($row['click'] ?? 0),
                (int) ($row['impression'] ?? 0),
                (int) ($row['sold_7_days'] ?? 0),
                (int) ($row['total_sold'] ?? 0),
            ];
        }

        $writer->writeRows($spreadsheetRows);
        $writer->close();

        $filename = 'CTR Summary '.now()->format('Y-m-d').'.xlsx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
