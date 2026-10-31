<?php

namespace App\Billing;

use App\Support\Money;

final readonly class CommissionSplit
{
    public function __construct(
        public Money $gross,
        public int $rateBps,
        public Money $commission,
        public Money $vendorEarnings,
    ) {}
}
