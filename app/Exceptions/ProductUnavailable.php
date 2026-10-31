<?php

namespace App\Exceptions;

final class ProductUnavailable extends ApiException
{
    public function __construct(public readonly string $sku)
    {
        parent::__construct("Product {$sku} is no longer available.");
    }

    public function errorCode(): string
    {
        return 'product_unavailable';
    }

    public function status(): int
    {
        return 422;
    }

    public function details(): array
    {
        return ['sku' => $this->sku];
    }
}
