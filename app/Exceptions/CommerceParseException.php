<?php

namespace App\Exceptions;

use Exception;

class CommerceParseException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $payloadSignature = '',
        public readonly ?string $eventId = null,
        public readonly ?string $actionType = null,
    ) {
        parent::__construct($message);
    }
}
