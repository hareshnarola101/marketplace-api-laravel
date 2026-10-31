<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

final class CancelOrder
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function handle(Order $order): Order
    {
        if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
            throw InvalidStateTransition::between('order', $order->status->value, OrderStatus::Cancelled->value);
        }

        // Cancel at the provider first: if the customer has already paid, this
        // fails and we keep the order instead of cancelling a paid one.
        if ($order->payment_intent_id !== null) {
            $this->gateway->cancelPaymentIntent($order->payment_intent_id);
        }

        return DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $order->markAsCancelled();

            $quantities = $order->items()
                ->whereNotNull('product_variant_id')
                ->get()
                ->groupBy('product_variant_id')
                ->map(fn ($items) => $items->sum('quantity'))
                ->sortKeys();

            foreach ($quantities as $variantId => $quantity) {
                ProductVariant::query()->whereKey($variantId)->increment('stock', (int) $quantity);
            }

            return $order;
        });
    }
}
