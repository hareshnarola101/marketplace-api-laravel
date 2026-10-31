<?php

namespace App\Exceptions;

final class PaymentGatewayError extends ApiException
{
    public function errorCode(): string
    {
        return 'payment_gateway_error';
    }

    public function status(): int
    {
        return 502;
    }
}
