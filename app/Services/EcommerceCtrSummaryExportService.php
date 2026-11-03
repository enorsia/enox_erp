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
        'Impression',
        'Click',
        'CTR',
        'CTR average',
        'Total sold amount',
        'Total sold qty',
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
            $soldQty = (int) ($row['total_qty'] ?? 0);
            $soldAmount = number_format((float) ($row['total_sold'] ?? 0), 2, '.', ',');
            $spreadsheetRows[] = [
                (string) ($row['department'] ?? ''),
                (string) ($row['category'] ?? $row['category_display'] ?? ''),
                (string) ($row['micro_category'] ?? '—'),
                (string) ($row['product_code'] ?? ''),
                (int) ($row['impression'] ?? 0),
                (int) ($row['click'] ?? 0),
                round((float) ($row['ctr'] ?? 0) * 100, 2),
                round((float) ($row['ctr_average'] ?? 0) * 100, 2),
                $soldAmount,
                $soldQty,
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
