<?php

namespace App\Actions\Payouts;

use App\Billing\VendorBalance;
use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientBalance;
use App\Models\LedgerEntry;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class RecordPayout
{
    public function __construct(private readonly VendorBalance $balance) {}

    public function handle(Vendor $vendor, Money $amount): LedgerEntry
    {
        return DB::transaction(function () use ($vendor, $amount) {
            // Lock the vendor row so two payouts can't both pass the balance check.
            Vendor::query()->lockForUpdate()->findOrFail($vendor->id);

            $available = $this->balance->summarize($vendor)->balance;

            if ($amount->greaterThan($available)) {
                throw new InsufficientBalance($amount, $available);
            }

            return $vendor->ledgerEntries()->create([
                'type' => LedgerEntryType::Payout,
                'amount' => $amount->negate()->amount,
                'currency' => $amount->currency,
                'description' => 'Payout',
            ]);
        });
    }
}
