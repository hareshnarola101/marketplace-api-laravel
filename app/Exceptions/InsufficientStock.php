<?php

namespace App\Exceptions;

final class InsufficientStock extends ApiException
{
    public function __construct(
        public readonly string $sku,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct("Not enough stock for {$sku}: requested {$requested}, available {$available}.");
    }

    public function errorCode(): string
    {
        return 'insufficient_stock';
    }

    public function status(): int
    {
        return 409;
    }

    public function details(): array
    {
        return ['sku' => $this->sku, 'requested' => $this->requested, 'available' => $this->available];
    }
}
