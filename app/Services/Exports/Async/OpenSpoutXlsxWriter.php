<?php

namespace App\Services\Exports\Async;

use App\Services\Exports\Async\Contracts\StreamingExportWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class OpenSpoutXlsxWriter implements StreamingExportWriter
{
    private const MAX_ROWS_PER_SHEET = 1000000;

    private SpreadsheetWriterProfile $profile;

    private SpreadsheetExportLayout $layout;

    private OpenSpoutSpreadsheetStyler $styler;

    private XlsxWriter $writer;

    private Options $options;

    private int $currentSheet = 1;

    private int $rowsInCurrentSheet = 0;

    private bool $headerWritten = false;

    private int $currentRow = 1;

    private int $lastDataRow = 0;

    private int $firstDataRow = SpreadsheetExportLayout::FIRST_DATA_ROW;

    private array $headings = [];

    /** @var array<string, mixed> */
    private array $headerContext = [];

    /** @var array<int, int> */
    private array $maxColumnContentLengths = [];

    public function __construct(string $filePath, ?SpreadsheetWriterProfile $profile = null)
    {
        $this->profile = $profile ?? SpreadsheetWriterProfile::sales();

        $this->options = new Options;

        $this->writer = new XlsxWriter($this->options);
        $this->writer->openToFile($filePath);
    }

    public function writeHeader(array $headings, array $context = []): void
    {
        $this->headings = $headings;
        $this->headerContext = $context;
        $this->layout = SpreadsheetExportLayout::fromProfile($this->profile, $headings);
        $this->styler = new OpenSpoutSpreadsheetStyler($this->layout);

        $this->configureSheetPresentation();
        $this->configureCompanyHeaderMerges();

        $this->writer->getCurrentSheet()->setName('Worksheet');

        foreach ($this->layout->companyHeaderTexts($context) as $line) {
            $this->writer->addRow(Row::fromValues([$line], $this->styler->companyHeaderStyle()));
            $this->currentRow++;
        }

        while ($this->currentRow < SpreadsheetExportLayout::HEADER_ROW) {
            $this->writeBlankRow();
        }

        $this->writeColumnHeaderRow($headings);

        $this->headerWritten = true;
        $this->rowsInCurrentSheet = SpreadsheetExportLayout::FIRST_DATA_ROW - 1;
        $this->currentRow = SpreadsheetExportLayout::FIRST_DATA_ROW;
        $this->firstDataRow = SpreadsheetExportLayout::FIRST_DATA_ROW;
    }

    public function writeRows(array $rows, array $meta = []): void
    {
        $orderLastIndices = $meta['order_last_indices'] ?? [];
        $batchMergeRanges = $this->normalizeMergeRanges($meta['merge_ranges'] ?? []);

        foreach ($rows as $index => $row) {
            if ($this->rowsInCurrentSheet >= self::MAX_ROWS_PER_SHEET) {
                $this->applyMergeRanges($batchMergeRanges);
                $batchMergeRanges = [];
                $this->startNewSheet();
            }

            $isOrderEnd = in_array($index, $orderLastIndices, true);
            $normalized = $this->normalizeRow($row);
            $this->trackColumnContentLengths($normalized);
            $columnStyles = [];

            foreach ($normalized as $columnIndex => $value) {
                $columnStyles[$columnIndex] = $this->styler->cellStyleForColumn(
                    $columnIndex,
                    $isOrderEnd,
                    $this->layout->shouldVerticallyCenterColumn($columnIndex),
                );
            }

            $this->writer->addRow(Row::fromValuesWithStyles($normalized, null, $columnStyles));

            $this->rowsInCurrentSheet++;
            $this->lastDataRow = $this->currentRow;
            $this->currentRow++;
        }

        $this->applyMergeRanges($batchMergeRanges);
    }

    public function close(): void
    {
        if ($this->headerWritten && $this->layout->totalsRow !== null && $this->lastDataRow >= $this->firstDataRow) {
            $this->writeTotalsRow();
        }

        $this->applyContentBasedColumnWidths();

        $this->writer->close();
    }

    public function getSheetCount(): int
    {
        return $this->currentSheet;
    }

    public function usesInlineFormatting(): bool
    {
        return true;
    }

    private function writeTotalsRow(): void
    {
        $spec = $this->layout->totalsRow;

        if ($spec === null) {
            return;
        }

        $values = $spec->buildRowValues($this->firstDataRow, $this->lastDataRow, $this->profile->columnCount);
        $columnStyles = [];

        for ($columnIndex = $spec->styleStartColumn; $columnIndex <= $spec->styleEndColumn; $columnIndex++) {
            $columnStyles[$columnIndex] = $this->styler->totalsRowStyle();
        }

        $this->writer->addRow(Row::fromValuesWithStyles($values, null, $columnStyles));
        $this->currentRow++;
    }

    private function writeColumnHeaderRow(array $headings): void
    {
        $values = [];

        for ($index = 0; $index < $this->profile->columnCount; $index++) {
            $values[] = $headings[$index] ?? '';
        }

        $this->writer->addRow(Row::fromValues($values, $this->styler->columnHeaderStyle()));
        $this->trackColumnContentLengths($values);
        $this->currentRow++;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int, mixed>
     */
    private function normalizeRow(array $values): array
    {
        $row = [];

        for ($index = 0; $index < $this->profile->columnCount; $index++) {
            $value = array_key_exists($index, $values) ? $values[$index] : '';
            $row[] = $this->castSpreadsheetValue($index, $value);
        }

        return $row;
    }

    private function castSpreadsheetValue(int $index, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if ($index === $this->profile->phoneColumn) {
            return $this->castPhoneValue($value);
        }

        if ($index === $this->profile->sizeColumn) {
            return $this->castNumericValue($value);
        }

        if ($this->layout !== null && $this->layout->shouldFormatAsQuantity($index)) {
            return $this->castIntegerValue($value);
        }

        if ($index === $this->profile->quantityColumn || $index === 0 || $index === $this->profile->orderNumberColumn) {
            return $this->castIntegerValue($value);
        }

        if ($index >= $this->profile->moneyColumnStart && $index <= $this->profile->moneyColumnEnd) {
            return $this->castFloatValue($value);
        }

        return $value;
    }

    private function castPhoneValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $digits = ltrim($value, '+');

        if ($digits !== '' && ctype_digit($digits) && ! str_starts_with($digits, '0')) {
            return (int) $digits;
        }

        return $value;
    }

    private function castNumericValue(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value) || ! is_numeric($value)) {
            return $value;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    private function castIntegerValue(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    private function castFloatValue(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    private function writeBlankRow(): void
    {
        $this->writer->addRow(Row::fromValues(array_fill(0, $this->profile->columnCount, '')));
        $this->currentRow++;
    }

    private function configureCompanyHeaderMerges(): void
    {
        for ($row = 1; $row <= $this->layout->companyHeaderLines; $row++) {
            $this->options->mergeCells(
                0,
                $row,
                $this->profile->mergeEndColumn,
                $row,
                $this->currentSheet - 1,
            );
        }
    }

    private function configureSheetPresentation(): void
    {
        $sheet = $this->writer->getCurrentSheet();
        $sheetView = (new SheetView)->setFreezeRow(SpreadsheetExportLayout::FIRST_DATA_ROW);
        $sheet->setSheetView($sheetView);

        foreach ($this->layout->columnWidths as $index => $width) {
            if ($this->profile->autoSizeColumnsFromContent) {
                continue;
            }

            $sheet->setColumnWidth($width, $index + 1);
        }
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function trackColumnContentLengths(array $values): void
    {
        if (! $this->profile->autoSizeColumnsFromContent) {
            return;
        }

        foreach ($values as $index => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $length = mb_strlen((string) $value);
            $this->maxColumnContentLengths[$index] = max($this->maxColumnContentLengths[$index] ?? 0, $length);
        }
    }

    private function applyContentBasedColumnWidths(): void
    {
        if (! $this->profile->autoSizeColumnsFromContent || $this->maxColumnContentLengths === []) {
            return;
        }

        $sheet = $this->writer->getCurrentSheet();

        for ($index = 0; $index < $this->profile->columnCount; $index++) {
            $length = $this->maxColumnContentLengths[$index] ?? 10;
            $sheet->setColumnWidth(
                SpreadsheetColumnWidthEstimator::widthFromCharacterCount($length),
                $index + 1,
            );
        }
    }

    /**
     * @param  array<int, mixed>  $ranges
     * @return array<int, array{column: int, start_row: int, end_row: int}>
     */
    private function normalizeMergeRanges(array $ranges): array
    {
        $normalized = [];

        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $column = (int) ($range['column'] ?? -1);
            $startRow = (int) ($range['start_row'] ?? 0);
            $endRow = (int) ($range['end_row'] ?? 0);

            if ($column < 0 || $startRow <= 0 || $endRow <= $startRow) {
                continue;
            }

            $normalized[] = [
                'column' => $column,
                'start_row' => $startRow,
                'end_row' => $endRow,
            ];
        }

        return $normalized;
    }

    /**
     * Apply merge ranges immediately so they are not kept in memory for the full export.
     *
     * @param  array<int, array{column: int, start_row: int, end_row: int}>  $ranges
     */
    private function applyMergeRanges(array $ranges, ?int $sheetIndex = null): void
    {
        if ($ranges === []) {
            return;
        }

        $sheetIndex ??= $this->currentSheet - 1;

        foreach ($ranges as $range) {
            $this->options->mergeCells(
                $range['column'],
                $range['start_row'],
                $range['column'],
                $range['end_row'],
                $sheetIndex,
            );
        }
    }

    private function startNewSheet(): void
    {
        $this->currentSheet++;
        $this->rowsInCurrentSheet = 0;
        $this->currentRow = 1;
        $this->firstDataRow = 2;

        $newSheet = $this->writer->addNewSheetAndMakeItCurrent();
        $newSheet->setName($this->profile->overflowSheetPrefix.' '.$this->currentSheet);

        $sheetView = (new SheetView)->setFreezeRow(2);
        $newSheet->setSheetView($sheetView);

        foreach ($this->layout->columnWidths as $index => $width) {
            if ($this->profile->autoSizeColumnsFromContent) {
                continue;
            }

            $newSheet->setColumnWidth($width, $index + 1);
        }

        if ($this->headerWritten) {
            $this->writeColumnHeaderRow($this->headings);
            $this->rowsInCurrentSheet = 1;
            $this->currentRow = 2;
            $this->firstDataRow = 2;
        }
    }
}
