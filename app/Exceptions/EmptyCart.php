<?php

namespace App\Exceptions;

final class EmptyCart extends ApiException
{
    public function __construct()
    {
        parent::__construct('Your cart is empty.');
    }

    public function errorCode(): string
    {
        return 'empty_cart';
    }

    public function status(): int
    {
        return 422;
    }
}
