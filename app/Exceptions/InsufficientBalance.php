<?php

namespace App\Exceptions;

use App\Support\Money;

final class InsufficientBalance extends ApiException
{
    public function __construct(public readonly Money $requested, public readonly Money $balance)
    {
        parent::__construct("Payout of {$requested->format()} exceeds the available balance of {$balance->format()}.");
    }

    public function errorCode(): string
    {
        return 'insufficient_balance';
    }

    public function status(): int
    {
        return 422;
    }
}
