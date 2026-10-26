<?php

namespace App\Services;

use App\Services\Exports\Async\OpenSpoutXlsxWriter;
use App\Services\Exports\Async\SpreadsheetWriterProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcomTrackerProductPerformanceExportService
{
    /** @var list<string> */
    private const HEADINGS = [
        'SL',
        'Product code',
        'Product name',
        'View',
        'Add',
        'Begin',
        'Proceed',
        'Paid',
        'Sale',
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function download(
        array $rows,
        Carbon $from,
        Carbon $to,
        string $filterSummary,
    ): BinaryFileResponse {
        $headings = self::HEADINGS;
        $profile = SpreadsheetWriterProfile::ecomProductPerformance($headings);

        $tempPath = storage_path('app/temp/ecom-product-performance-'.uniqid('', true).'.xlsx');
        File::ensureDirectoryExists(dirname($tempPath));

        $writer = new OpenSpoutXlsxWriter($tempPath, $profile);
        $writer->writeHeader($headings, [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
            'filter_summary' => $filterSummary,
        ]);

        $spreadsheetRows = [];
        foreach ($rows as $index => $product) {
            $code = trim((string) ($product['code'] ?? $product['product_code'] ?? ''));
            $name = trim((string) ($product['name'] ?? ''));
            $spreadsheetRows[] = [
                $index + 1,
                $code,
                $name,
                (int) ($product['views'] ?? 0),
                (int) ($product['add_to_cart'] ?? 0),
                (int) ($product['begin_checkout'] ?? 0),
                (int) ($product['proceed_checkout'] ?? 0),
                (int) ($product['paid'] ?? 0),
                round((float) ($product['revenue'] ?? 0), 2),
            ];
        }

        $writer->writeRows($spreadsheetRows);
        $writer->close();

        $filename = 'Product performance '.$from->format('Y-m-d').' to '.$to->format('Y-m-d').'.xlsx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
