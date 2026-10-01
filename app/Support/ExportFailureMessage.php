<?php

namespace App\Support;

use Throwable;

final class ExportFailureMessage
{
    public function __construct(
        private readonly string $userMessage,
        private readonly string $detailMessage,
    ) {}

    public static function from(Throwable $throwable): self
    {
        $detail = $throwable->getMessage();

        return new self(
            userMessage: 'Export failed. Please try again or contact support if this continues.',
            detailMessage: $detail !== '' ? $detail : 'Unknown export error',
        );
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }

    public function detailMessage(): string
    {
        return $this->detailMessage;
    }

    public static function forDisplay(?string $message): ?string
    {
        return filled($message) ? $message : null;
    }
}
