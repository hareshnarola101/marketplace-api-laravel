<?php

namespace App\Payments;

final readonly class PaymentIntent
{
    public function __construct(
        public string $id,
        public string $clientSecret,
        public string $status,
    ) {}
}
