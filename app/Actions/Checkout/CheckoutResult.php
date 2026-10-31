<?php

namespace App\Actions\Checkout;

use App\Models\Order;
use App\Payments\PaymentIntent;

final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public ?PaymentIntent $paymentIntent,
        public bool $wasCreated,
    ) {}
}
