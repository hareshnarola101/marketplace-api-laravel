<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    // Credit: what the customer paid for the vendor's items.
    case Sale = 'sale';
    // Debit: the platform's cut of a sale.
    case Commission = 'commission';
    // Debit: money transferred out to the vendor.
    case Payout = 'payout';
}
