<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Penolakan lifecycle dengan kode stabil untuk API/UI.
 */
class MedicalRecordLifecycleException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 409,
        public readonly array $context = []
    ) {
        parent::__construct($message);
    }
}
