<?php

namespace App\Billing;

use App\Models\Vendor;
use App\Support\Money;
use InvalidArgumentException;

final class CommissionCalculator
{
    public const BPS_SCALE = 10_000;

    public function __construct(private readonly int $defaultRateBps)
    {
        $this->assertValidRate($defaultRateBps);
    }

    public function rateFor(Vendor $vendor): int
    {
        return $vendor->commission_rate_bps ?? $this->defaultRateBps;
    }

    public function forVendor(Vendor $vendor, Money $gross): CommissionSplit
    {
        return $this->split($gross, $this->rateFor($vendor));
    }

    /**
     * Commission is rounded half-up to the nearest cent and the vendor gets the
     * remainder, so commission + earnings always equals the gross amount.
     */
    public function split(Money $gross, int $rateBps): CommissionSplit
    {
        $this->assertValidRate($rateBps);

        if ($gross->isNegative()) {
            throw new InvalidArgumentException('Cannot take commission on a negative amount.');
        }

        $commission = Money::of(
            intdiv($gross->amount * $rateBps + intdiv(self::BPS_SCALE, 2), self::BPS_SCALE),
            $gross->currency,
        );

        return new CommissionSplit($gross, $rateBps, $commission, $gross->minus($commission));
    }

    private function assertValidRate(int $rateBps): void
    {
        if ($rateBps < 0 || $rateBps > self::BPS_SCALE) {
            throw new InvalidArgumentException("Commission rate must be between 0 and 10000 bps, got [{$rateBps}].");
        }
    }
}
