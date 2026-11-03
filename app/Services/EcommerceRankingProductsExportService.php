<?php

namespace App\Services;

use App\Services\Exports\Async\OpenSpoutXlsxWriter;
use App\Services\Exports\Async\SpreadsheetWriterProfile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcommerceRankingProductsExportService
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function download(array $rows, string $filterSummary, int $sortingSalesDays): BinaryFileResponse
    {
        $rollingSoldLabel = max(1, $sortingSalesDays).' days sold';
        $headings = [
            'Department',
            'Category',
            'Micro category',
            'Rank',
            'Product code',
            'CTR',
            'CTR average',
            $rollingSoldLabel,
            'Total sold',
            'Click',
            'Impression',
            'New in',
            'Pin position',
            'Selling fast',
            'Stock out',
        ];

        $profile = SpreadsheetWriterProfile::ecomRankingProducts($headings);

        $tempPath = storage_path('app/temp/ecom-ranking-products-'.uniqid('', true).'.xlsx');
        File::ensureDirectoryExists(dirname($tempPath));

        $writer = new OpenSpoutXlsxWriter($tempPath, $profile);
        $writer->writeHeader($headings, [
            'filter_summary' => $filterSummary,
        ]);

        $spreadsheetRows = [];
        foreach ($rows as $row) {
            $pinSerial = $row['pin_serial'] ?? null;
            $spreadsheetRows[] = [
                (string) ($row['listing_department'] ?? '—'),
                (string) ($row['listing_category'] ?? '—'),
                (string) ($row['listing_micro_category'] ?? '—'),
                (int) ($row['serial_number'] ?? 0),
                (string) ($row['product_code'] ?? ''),
                round((float) ($row['ctr'] ?? 0), 4),
                round((float) ($row['ctr_average'] ?? 0), 4),
                (int) ($row['total_sold_rolling'] ?? 0),
                (int) ($row['total_sold'] ?? 0),
                (int) ($row['click'] ?? 0),
                (int) ($row['impression'] ?? 0),
                $this->exportFlagValue((int) ($row['is_new_in'] ?? 0)),
                $pinSerial !== null ? (int) $pinSerial : '—',
                $this->exportFlagValue((int) ($row['is_selling_fast'] ?? 0)),
                $this->exportFlagValue((int) ($row['is_stock_out'] ?? 0)),
            ];
        }

        $writer->writeRows($spreadsheetRows);
        $writer->close();

        $filename = 'Product Ranking '.now()->format('Y-m-d').'.xlsx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function exportFlagValue(int $value): string
    {
        return $value === 1 ? 'Yes' : '—';
    }
}
