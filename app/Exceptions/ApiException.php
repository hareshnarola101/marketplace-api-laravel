<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base for business-rule failures that should reach the client with a
 * stable machine-readable code instead of a generic 500.
 */
abstract class ApiException extends RuntimeException
{
    abstract public function errorCode(): string;

    abstract public function status(): int;

    /** @return array<string, mixed>|null */
    public function details(): ?array
    {
        return null;
    }
}
