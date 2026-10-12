<?php

namespace App\Services\Exports\Async;

use App\Services\Exports\Async\Contracts\StreamingExportWriter;
use Carbon\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;

class OpenSpoutCsvWriter implements StreamingExportWriter
{
    private SpreadsheetWriterProfile $profile;

    private CsvWriter $writer;

    private int $dataRowCount = 0;

    /** @var array<int, float> */
    private array $totals = [];

    /** @var array<string, mixed> */
    private array $headerContext = [];

    public function __construct(string $filePath, ?SpreadsheetWriterProfile $profile = null)
    {
        $this->profile = $profile ?? SpreadsheetWriterProfile::sales();

        $this->writer = new CsvWriter();
        $this->writer->openToFile($filePath);
    }

    public function writeHeader(array $headings, array $context = []): void
    {
        $this->headerContext = $context;

        foreach (['PFD ENORSIA UK LTD', $this->profile->reportTitle, $this->dateRangeLabel()] as $line) {
            $this->writer->addRow(Row::fromValues($this->padRow([$line])));
        }

        $this->writeBlankRow();
        $this->writeBlankRow();
        $this->writer->addRow(Row::fromValues($this->padRow($headings)));
    }

    public function writeRows(array $rows, array $meta = []): void
    {
        foreach ($rows as $row) {
            $normalized = $this->normalizeRow($row);
            $this->accumulateTotals($normalized);
            $this->writer->addRow(Row::fromValues($this->formatRowForOutput($normalized)));
            $this->dataRowCount++;
        }
    }

    public function close(): void
    {
        $this->writeTotalsRow();
        $this->writer->close();
    }

    public function getSheetCount(): int
    {
        return 1;
    }

    private function dateRangeLabel(): string
    {
        $startDate = $this->headerContext['start_date'] ?? null;
        $endDate = $this->headerContext['end_date'] ?? null;

        if (empty($startDate) || empty($endDate)) {
            return '';
        }

        return 'FROM '.Carbon::parse($startDate)->format('m/d/Y')
            .' TO '.Carbon::parse($endDate)->format('m/d/Y');
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int, mixed>
     */
    private function normalizeRow(array $values): array
    {
        $row = [];
        $columnCount = $this->profile->columnCount;

        for ($index = 0; $index < $columnCount; $index++) {
            $row[] = array_key_exists($index, $values) ? $values[$index] : '';
        }

        return $row;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string|int|float>
     */
    private function formatRowForOutput(array $row): array
    {
        $formatted = $row;

        for ($column = $this->profile->moneyColumnStart; $column <= $this->profile->moneyColumnEnd; $column++) {
            $value = $row[$column] ?? null;

            if ($value === null || $value === '') {
                $formatted[$column] = '';
                continue;
            }

            if (is_numeric($value)) {
                $formatted[$column] = number_format((float) $value, 2, '.', '');
            }
        }

        return $formatted;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function accumulateTotals(array $row): void
    {
        $quantity = $row[$this->profile->quantityColumn] ?? null;

        if ($quantity !== null && $quantity !== '' && is_numeric($quantity)) {
            $this->totals[$this->profile->quantityColumn] = ($this->totals[$this->profile->quantityColumn] ?? 0) + (float) $quantity;
        }

        for ($column = $this->profile->moneyColumnStart; $column <= $this->profile->moneyColumnEnd; $column++) {
            $value = $row[$column] ?? null;

            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }

            $this->totals[$column] = ($this->totals[$column] ?? 0) + (float) $value;
        }
    }

    private function writeTotalsRow(): void
    {
        if ($this->dataRowCount === 0) {
            return;
        }

        $row = array_fill(0, $this->profile->columnCount, '');
        $row[$this->profile->totalLabelColumn] = 'Total';
        $row[$this->profile->quantityColumn] = (string) (int) round($this->totals[$this->profile->quantityColumn] ?? 0);

        for ($column = $this->profile->moneyColumnStart; $column <= $this->profile->moneyColumnEnd; $column++) {
            $sum = $this->totals[$column] ?? 0;

            if ($column === $this->profile->couponPercentColumn) {
                $row[$column] = number_format($sum, 2, '.', '');
                continue;
            }

            $row[$column] = '£'.number_format($sum, 2, '.', '');
        }

        $this->writer->addRow(Row::fromValues($row));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    private function padRow(array $values): array
    {
        $row = array_fill(0, $this->profile->columnCount, '');

        foreach ($values as $index => $value) {
            $row[$index] = $value;
        }

        return $row;
    }

    private function writeBlankRow(): void
    {
        $this->writer->addRow(Row::fromValues(array_fill(0, $this->profile->columnCount, '')));
    }
}
