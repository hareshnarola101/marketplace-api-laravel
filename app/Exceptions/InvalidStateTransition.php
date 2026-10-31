<?php

namespace App\Exceptions;

final class InvalidStateTransition extends ApiException
{
    public static function between(string $subject, string $from, string $to): self
    {
        return new self("Cannot move {$subject} from {$from} to {$to}.");
    }

    public function errorCode(): string
    {
        return 'invalid_state_transition';
    }

    public function status(): int
    {
        return 409;
    }
}
