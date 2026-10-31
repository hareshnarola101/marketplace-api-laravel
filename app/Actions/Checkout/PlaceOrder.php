<?php

namespace App\Actions\Checkout;

use App\Billing\CommissionCalculator;
use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\EmptyCart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Payments\PaymentGateway;
use App\Payments\PaymentIntent;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Turns the customer's cart into one order split into a sub-order per vendor.
 *
 * Stock is checked and decremented while the variant rows are locked, so two
 * customers racing for the last unit can't both win. The payment intent is
 * created after the transaction commits to avoid holding locks during an
 * HTTP call to the payment provider.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly CommissionCalculator $commission,
        private readonly PaymentGateway $gateway,
    ) {}

    public function handle(User $customer, string $idempotencyKey): CheckoutResult
    {
        $order = $this->findByKey($customer, $idempotencyKey);
        $wasCreated = false;

        if ($order === null) {
            try {
                $order = DB::transaction(fn () => $this->createOrder($customer, $idempotencyKey));
                $wasCreated = true;
            } catch (EmptyCart|UniqueConstraintViolationException $e) {
                // A concurrent request with the same key may have placed the order
                // (and emptied the cart) while this one was waiting on a lock.
                $order = $this->findByKey($customer, $idempotencyKey) ?? throw $e;
            }
        }

        $intent = $order->status === OrderStatus::PendingPayment ? $this->paymentIntentFor($order) : null;

        return new CheckoutResult($order, $intent, $wasCreated);
    }

    private function createOrder(User $customer, string $idempotencyKey): Order
    {
        // Locking the cart rows serialises concurrent checkouts by the same customer.
        $cartItems = $customer->cartItems()->lockForUpdate()->get();

        if ($cartItems->isEmpty()) {
            throw new EmptyCart;
        }

        $order = $customer->orders()->create([
            'status' => OrderStatus::PendingPayment,
            'currency' => config()->string('marketplace.currency'),
            'total' => 0,
            'idempotency_key' => $idempotencyKey,
        ]);

        $variants = $this->lockVariants($cartItems);

        foreach ($cartItems as $item) {
            $variants[$item->product_variant_id]->ensureCanBeBought($item->quantity);
        }

        $total = Money::zero($order->currency);

        $byVendor = $cartItems->groupBy(fn (CartItem $item) => $variants[$item->product_variant_id]->product->vendor_id);

        foreach ($byVendor as $vendorItems) {
            $total = $total->plus($this->createVendorOrder($order, $vendorItems, $variants));
        }

        foreach ($cartItems as $item) {
            $variants[$item->product_variant_id]->decrement('stock', $item->quantity);
        }

        $order->update(['total' => $total->amount]);
        $customer->cartItems()->delete();

        return $order;
    }

    /**
     * Rows are locked in primary-key order so two checkouts touching the same
     * variants always acquire locks in the same sequence and can't deadlock.
     *
     * @param  Collection<int, CartItem>  $cartItems
     * @return Collection<int, ProductVariant>
     */
    private function lockVariants(Collection $cartItems): Collection
    {
        $variants = ProductVariant::query()
            ->whereIn('id', $cartItems->pluck('product_variant_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->load('product.vendor')
            ->keyBy('id');

        return $variants;
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function createVendorOrder(Order $order, Collection $items, Collection $variants): Money
    {
        $vendor = $variants[$items->first()?->product_variant_id]->product->vendor;
        $subtotal = Money::zero($order->currency);
        $lines = [];

        foreach ($items as $item) {
            $variant = $variants[$item->product_variant_id];
            $lineTotal = $variant->priceMoney()->times($item->quantity);
            $subtotal = $subtotal->plus($lineTotal);

            $lines[] = [
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'unit_price' => $variant->price,
                'quantity' => $item->quantity,
                'line_total' => $lineTotal->amount,
            ];
        }

        $split = $this->commission->forVendor($vendor, $subtotal);

        $vendorOrder = $order->vendorOrders()->create([
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::PendingPayment,
            'subtotal' => $subtotal->amount,
            'commission_rate_bps' => $split->rateBps,
            'commission_amount' => $split->commission->amount,
            'vendor_earnings' => $split->vendorEarnings->amount,
        ]);

        $vendorOrder->items()->createMany($lines);

        return $subtotal;
    }

    private function paymentIntentFor(Order $order): PaymentIntent
    {
        if ($order->payment_intent_id !== null) {
            return $this->gateway->retrievePaymentIntent($order->payment_intent_id);
        }

        // The provider-side idempotency key means a retry after a timeout
        // gets the same intent back rather than a second charge.
        $intent = $this->gateway->createPaymentIntent(
            $order->totalMoney(),
            "order-{$order->id}",
            ['order_id' => (string) $order->id],
        );

        $order->update(['payment_intent_id' => $intent->id]);

        return $intent;
    }

    private function findByKey(User $customer, string $idempotencyKey): ?Order
    {
        return $customer->orders()->where('idempotency_key', $idempotencyKey)->first();
    }
}
