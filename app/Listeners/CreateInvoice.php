<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(5)]
#[Backoff(10, 60, 300)]
final class CreateInvoice implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        Invoice::query()->firstOrCreate(
            ['order_id' => $order->id],
            [
                'number' => sprintf('INV-%s-%06d', ($order->paid_at ?? now())->format('Y'), $order->id),
                'total' => $order->total,
                'currency' => $order->currency,
                'issued_at' => now(),
            ],
        );
    }

    public function failed(OrderPaid $event, Throwable $exception): void
    {
        Log::error('Invoice could not be created.', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
