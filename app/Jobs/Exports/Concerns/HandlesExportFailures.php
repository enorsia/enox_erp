<?php

namespace App\Jobs\Exports\Concerns;

use App\Models\UserExport;
use App\Support\ExportFailureMessage;
use Throwable;

trait HandlesExportFailures
{
    protected function notifyExportFailure(UserExport $export, Throwable $e): void
    {
        $export->update([
            'status' => UserExport::STATUS_FAILED,
            'error_message' => ExportFailureMessage::from($e)->userMessage(),
            'completed_at' => now(),
        ]);
    }

    protected function persistPermanentExportFailure(UserExport $export, Throwable $e): void
    {
        $export->update([
            'status' => UserExport::STATUS_FAILED,
            'error_message' => ExportFailureMessage::from($e)->userMessage(),
            'completed_at' => now(),
        ]);
    }
}
