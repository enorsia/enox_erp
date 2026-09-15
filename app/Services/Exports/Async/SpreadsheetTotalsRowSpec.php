<?php

namespace App\Services\Exports\Async;

final class SpreadsheetTotalsRowSpec
{
    /**
     * @param  array<int, int>  $poundSumColumns  0-based column indexes summed with £ prefix
     * @param  array<int, int>  $plainSumColumns  0-based column indexes summed with empty prefix
     */
    public function __construct(
        public readonly int $labelColumn,
        public readonly ?int $integerSumColumn,
        public readonly array $poundSumColumns,
        public readonly array $plainSumColumns,
        public readonly int $styleStartColumn,
        public readonly int $styleEndColumn,
    ) {}

    /**
     * @return array<int, string>
     */
    public function buildRowValues(int $firstDataRow, int $lastDataRow, int $columnCount): array
    {
        $values = array_fill(0, $columnCount, '');

        $values[$this->labelColumn] = 'Total';

        if ($this->integerSumColumn !== null) {
            $column = SpreadsheetCellAddress::columnLetter($this->integerSumColumn);
            $values[$this->integerSumColumn] = "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})";
        }

        foreach ($this->poundSumColumns as $columnIndex) {
            $column = SpreadsheetCellAddress::columnLetter($columnIndex);
            $values[$columnIndex] = '="£" & TEXT(SUM('.$column.$firstDataRow.':'.$column.$lastDataRow.'), "0.00")';
        }

        foreach ($this->plainSumColumns as $columnIndex) {
            $column = SpreadsheetCellAddress::columnLetter($columnIndex);
            $values[$columnIndex] = '="" & TEXT(SUM('.$column.$firstDataRow.':'.$column.$lastDataRow.'), "0.00")';
        }

        return $values;
    }
}
