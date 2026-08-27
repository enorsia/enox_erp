<?php

namespace App\Support;

use Illuminate\Database\QueryException;

final class CommerceSqlHelper
{
    public static function isDuplicateKeyException(QueryException $exception, ?string $indexName = null): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($sqlState !== '23000' && $driverCode !== 1062) {
            return false;
        }

        if ($indexName === null) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        if (str_contains($message, strtolower($indexName))) {
            return true;
        }

        return str_contains($message, 'unique constraint failed');
    }
}
