<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Notifications\VendorOrderReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
#[Backoff(30, 120)]
final class NotifyVendorsOfPaidOrder implements ShouldQueue
{
    /**
     * Each notification is queued on its own, so one vendor's mail failure
     * retries only that vendor instead of re-sending to everyone.
     */
    public function handle(OrderPaid $event): void
    {
        $event->order->loadMissing('vendorOrders.vendor.user');

        foreach ($event->order->vendorOrders as $vendorOrder) {
            $vendorOrder->vendor->user->notify(new VendorOrderReceived($vendorOrder));
        }
    }
}
