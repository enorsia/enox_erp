<?php

namespace App\Services\Exports\Async;

final class SpreadsheetCellAddress
{
    public static function columnLetter(int $zeroBasedIndex): string
    {
        $index = $zeroBasedIndex;
        $letters = '';

        do {
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letters;
    }
}
