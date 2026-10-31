<?php

namespace App\Billing;

use App\Enums\LedgerEntryType;
use App\Models\Vendor;
use App\Support\Money;

/**
 * Balances are always derived from the ledger, never stored, so there is
 * no second number that can drift out of sync with the entries.
 */
final class VendorBalance
{
    public function __construct(private readonly CommissionCalculator $commission) {}

    public function summarize(Vendor $vendor): PayoutSummary
    {
        $currency = config()->string('marketplace.currency');

        /** @var array<string, int|string> $totals */
        $totals = $vendor->ledgerEntries()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $sum = fn (LedgerEntryType $type) => Money::of((int) ($totals[$type->value] ?? 0), $currency);

        $gross = $sum(LedgerEntryType::Sale);
        $commission = $sum(LedgerEntryType::Commission)->negate();
        $paidOut = $sum(LedgerEntryType::Payout)->negate();
        $net = $gross->minus($commission);

        return new PayoutSummary(
            grossSales: $gross,
            commission: $commission,
            netEarnings: $net,
            paidOut: $paidOut,
            balance: $net->minus($paidOut),
            commissionRateBps: $this->commission->rateFor($vendor),
        );
    }
}
