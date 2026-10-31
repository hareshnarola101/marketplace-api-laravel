<?php

namespace App\Listeners;

use App\Enums\LedgerEntryType;
use App\Events\OrderPaid;
use App\Models\LedgerEntry;
use App\Models\VendorOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(5)]
#[Backoff(10, 60, 300)]
final class RecordVendorEarnings implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order->loadMissing('vendorOrders');

        // firstOrCreate plus the unique (vendor_order_id, type) index makes retries safe.
        DB::transaction(function () use ($order) {
            foreach ($order->vendorOrders as $vendorOrder) {
                $this->book($vendorOrder, LedgerEntryType::Sale, $vendorOrder->subtotal, $order->currency);
                $this->book($vendorOrder, LedgerEntryType::Commission, -$vendorOrder->commission_amount, $order->currency);
            }
        });
    }

    public function failed(OrderPaid $event, Throwable $exception): void
    {
        Log::critical('Vendor ledger was not updated for a paid order.', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }

    private function book(VendorOrder $vendorOrder, LedgerEntryType $type, int $amount, string $currency): void
    {
        LedgerEntry::query()->firstOrCreate(
            ['vendor_order_id' => $vendorOrder->id, 'type' => $type],
            [
                'vendor_id' => $vendorOrder->vendor_id,
                'amount' => $amount,
                'currency' => $currency,
                'description' => "Order #{$vendorOrder->order_id}",
            ],
        );
    }
}
