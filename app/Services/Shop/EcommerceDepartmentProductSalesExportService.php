<?php

namespace App\Services\Shop;

use App\Services\Exports\Async\OpenSpoutXlsxWriter;
use App\Services\Exports\Async\SpreadsheetWriterProfile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcommerceDepartmentProductSalesExportService
{
    /** @var list<string> */
    private const HEADINGS = [
        'Department',
        'Product code',
        'Total qty',
        'Sold amount',
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function download(array $rows, string $filterSummary, string $dateFrom, string $dateTo): BinaryFileResponse
    {
        $profile = SpreadsheetWriterProfile::ecomDepartmentProductSales(self::HEADINGS);

        $tempPath = storage_path('app/temp/ecom-department-product-sales-'.uniqid('', true).'.xlsx');
        File::ensureDirectoryExists(dirname($tempPath));

        $writer = new OpenSpoutXlsxWriter($tempPath, $profile);
        $writer->writeHeader(self::HEADINGS, [
            'filter_summary' => $filterSummary,
            'start_date' => $dateFrom,
            'end_date' => $dateTo,
        ]);

        $spreadsheetRows = [];
        foreach ($rows as $row) {
            $spreadsheetRows[] = [
                (string) ($row['department_name'] ?? ''),
                (string) ($row['product_code'] ?? ''),
                (int) ($row['total_qty'] ?? 0),
                round((float) ($row['total_sale'] ?? 0), 2),
            ];
        }

        $writer->writeRows($spreadsheetRows);
        $writer->close();

        $filename = 'Department Product Sales '.now()->format('Y-m-d').'.xlsx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}