<?php

namespace App\Services\Exports\Async\Contracts;

interface StreamingExportWriter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function writeHeader(array $headings, array $context = []): void;

    /**
     * @param  array<int, array<int|string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     */
    public function writeRows(array $rows, array $meta = []): void;

    public function close(): void;

    public function getSheetCount(): int;
}
