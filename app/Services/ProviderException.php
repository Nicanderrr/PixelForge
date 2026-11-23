<?php

namespace App\Services;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable,
        public readonly int $retryAfter = 0,
        public readonly bool $ambiguous = false,
    ) {
        parent::__construct($errorCode);
    }
}
