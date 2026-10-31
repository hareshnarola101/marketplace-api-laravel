<?php

namespace App\Console\Commands;

use App\Actions\Payments\HandlePaymentWebhook;
use App\Models\Order;
use App\Payments\PaymentEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Local-only stand-in for "the customer paid": feeds a payment_intent.succeeded
 * event through the same handler the webhook endpoint uses.
 */
#[Signature('orders:simulate-payment {order : Order id}')]
#[Description('Mark an order as paid through the webhook handler (fake gateway only)')]
class SimulatePayment extends Command
{
    public function handle(HandlePaymentWebhook $handler): int
    {
        if (config('marketplace.payments.gateway') !== 'fake') {
            $this->error('Only available with PAYMENT_GATEWAY=fake. Use the Stripe CLI for Stripe.');

            return self::FAILURE;
        }

        $order = Order::query()->findOrFail($this->argument('order'));

        if ($order->payment_intent_id === null) {
            $this->error('This order has no payment intent yet.');

            return self::FAILURE;
        }

        $handler->handle(new PaymentEvent(
            id: 'evt_fake_'.Str::random(24),
            type: PaymentEvent::PAYMENT_SUCCEEDED,
            paymentIntentId: $order->payment_intent_id,
            amount: $order->total,
            currency: $order->currency,
        ));

        $this->info("Order #{$order->id} is now {$order->refresh()->status->value}.");

        return self::SUCCESS;
    }
}
