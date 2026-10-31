<?php

use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\PaymentGatewayError;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Payments\PaymentGateway;

function variantFor(Vendor $vendor, int $price, int $stock = 10): ProductVariant
{
    return Product::factory()->for($vendor)->withVariant($price, $stock)->create()->variants->first();
}

it('splits a multi-vendor cart into one sub-order per vendor', function () {
    $shoes = Vendor::factory()->approved()->create();                         // default 10%
    $books = Vendor::factory()->approved()->withCommission(1500)->create();   // 15%

    $sneaker = variantFor($shoes, 4999);
    $sock = variantFor($shoes, 899);
    $novel = variantFor($books, 1550);

    $customer = signIn();
    fillCart($customer, [[$sneaker, 1], [$sock, 3], [$novel, 2]]);

    $response = checkout()->assertCreated();

    $response->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.total.amount', 4999 + 3 * 899 + 2 * 1550)
        ->assertJsonCount(2, 'data.vendor_orders')
        ->assertJsonPath('payment.status', 'requires_payment_method');

    $order = Order::query()->with('vendorOrders')->sole();
    $shoeOrder = $order->vendorOrders->firstWhere('vendor_id', $shoes->id);
    $bookOrder = $order->vendorOrders->firstWhere('vendor_id', $books->id);

    // 76.96 at 10% -> 7.696 -> 7.70
    expect($shoeOrder->subtotal)->toBe(7696)
        ->and($shoeOrder->commission_amount)->toBe(770)
        ->and($shoeOrder->vendor_earnings)->toBe(6926)
        ->and($shoeOrder->status)->toBe(VendorOrderStatus::PendingPayment);

    // 31.00 at 15% -> 4.65
    expect($bookOrder->subtotal)->toBe(3100)
        ->and($bookOrder->commission_rate_bps)->toBe(1500)
        ->and($bookOrder->commission_amount)->toBe(465)
        ->and($bookOrder->vendor_earnings)->toBe(2635);

    expect($order->payment_intent_id)->toStartWith('pi_fake_');
});

it('decrements stock and empties the cart', function () {
    $variant = variantFor(Vendor::factory()->approved()->create(), 1000, stock: 5);
    $customer = signIn();
    fillCart($customer, [[$variant, 2]]);

    checkout()->assertCreated();

    expect($variant->fresh()->stock)->toBe(3)
        ->and($customer->cartItems()->count())->toBe(0);
});

it('requires an Idempotency-Key header', function () {
    signIn();

    $this->postJson('/api/v1/checkout')
        ->assertStatus(422)
        ->assertJsonPath('error.details.idempotency_key.0', 'The Idempotency-Key header is required.');
});

it('returns the same order when the same key is replayed', function () {
    $variant = variantFor(Vendor::factory()->approved()->create(), 1000, stock: 5);
    $customer = signIn();
    fillCart($customer, [[$variant, 2]]);

    $first = checkout('checkout-key-123')->assertCreated();

    // The client retries (e.g. after a timeout) with a refilled cart.
    fillCart($customer, [[$variant, 1]]);
    $second = checkout('checkout-key-123')->assertOk()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($second->json('payment.client_secret'))->toBe($first->json('payment.client_secret'))
        ->and(Order::query()->count())->toBe(1)
        ->and($variant->fresh()->stock)->toBe(3);
});

it('treats keys as per-customer', function () {
    $variant = variantFor(Vendor::factory()->approved()->create(), 1000);

    foreach (range(1, 2) as $i) {
        fillCart($customer = signIn(), [[$variant, 1]]);
        checkout('shared-key-value')->assertCreated();
    }

    expect(Order::query()->count())->toBe(2);
});

it('refuses to check out an empty cart', function () {
    signIn();

    checkout()->assertStatus(422)->assertJsonPath('error.code', 'empty_cart');
});

it('rejects products that became unavailable after being added to the cart', function () {
    $variant = variantFor(Vendor::factory()->approved()->create(), 1000);
    $customer = signIn();
    fillCart($customer, [[$variant, 1]]);

    $variant->product->delete();

    checkout()->assertStatus(422)->assertJsonPath('error.code', 'product_unavailable');
    expect(Order::query()->count())->toBe(0);
});

it('keeps the order and lets the client retry when the payment provider is down', function () {
    $variant = variantFor(Vendor::factory()->approved()->create(), 1000, stock: 5);
    $customer = signIn();
    fillCart($customer, [[$variant, 1]]);

    $real = app(PaymentGateway::class);
    $failing = Mockery::mock(PaymentGateway::class);
    $failing->shouldReceive('createPaymentIntent')->once()->andThrow(new PaymentGatewayError('Payment provider error: timeout'));
    app()->instance(PaymentGateway::class, $failing);

    checkout('retry-me-please')->assertStatus(502)->assertJsonPath('error.code', 'payment_gateway_error');

    app()->instance(PaymentGateway::class, $real);

    checkout('retry-me-please')->assertOk()->assertJsonPath('payment.status', 'requires_payment_method');

    expect(Order::query()->sole()->status)->toBe(OrderStatus::PendingPayment)
        ->and($variant->fresh()->stock)->toBe(4);
});
