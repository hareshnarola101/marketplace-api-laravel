<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Payments\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Providers deliver webhooks at least once, so the same event can arrive
 * several times, out of order, or concurrently. Two guards keep this safe:
 * the event id is recorded in the same transaction as the state change, and
 * the order row is locked and its status checked before marking it paid.
 */
final class HandlePaymentWebhook
{
    public function handle(PaymentEvent $event): void
    {
        DB::transaction(function () use ($event) {
            $isNew = ProcessedWebhookEvent::query()->insertOrIgnore([
                'id' => $event->id,
                'type' => $event->type,
                'processed_at' => now(),
            ]) === 1;

            if (! $isNew) {
                return;
            }

            if ($event->type === PaymentEvent::PAYMENT_SUCCEEDED) {
                $this->markOrderPaid($event);
            }
        });
    }

    private function markOrderPaid(PaymentEvent $event): void
    {
        $order = Order::query()
            ->where('payment_intent_id', $event->paymentIntentId)
            ->lockForUpdate()
            ->first();

        if ($order === null) {
            Log::warning('Payment succeeded for an unknown payment intent.', ['payment_intent' => $event->paymentIntentId]);

            return;
        }

        if ($order->status === OrderStatus::Paid) {
            return;
        }

        if ($order->status !== OrderStatus::PendingPayment) {
            Log::error('Payment succeeded for an order that is not awaiting payment; needs a refund.', [
                'order_id' => $order->id,
                'status' => $order->status->value,
            ]);

            return;
        }

        if ($event->amount !== $order->total || $event->currency !== $order->currency) {
            Log::error('Paid amount does not match order total.', [
                'order_id' => $order->id,
                'expected' => $order->totalMoney()->toArray(),
                'received' => ['amount' => $event->amount, 'currency' => $event->currency],
            ]);

            return;
        }

        $order->markAsPaid();

        OrderPaid::dispatch($order);
    }
}
