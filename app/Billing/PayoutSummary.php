<?php

namespace App\Billing;

use App\Support\Money;

final readonly class PayoutSummary
{
    public function __construct(
        public Money $grossSales,
        public Money $commission,
        public Money $netEarnings,
        public Money $paidOut,
        public Money $balance,
        public int $commissionRateBps,
    ) {}
}
