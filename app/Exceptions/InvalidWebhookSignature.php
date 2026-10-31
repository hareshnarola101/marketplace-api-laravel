<?php

namespace App\Exceptions;

final class InvalidWebhookSignature extends ApiException
{
    public function __construct()
    {
        parent::__construct('Webhook signature verification failed.');
    }

    public function errorCode(): string
    {
        return 'invalid_signature';
    }

    public function status(): int
    {
        return 400;
    }
}
