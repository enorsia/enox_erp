<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class ExportLogger
{
    public static function info(string $message, array $context = []): void
    {
        Log::channel('daily')->info('[export] '.$message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::channel('daily')->warning('[export] '.$message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        Log::channel('daily')->debug('[export] '.$message, $context);
    }
}
