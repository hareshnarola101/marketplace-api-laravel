<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorOrder;

function placedOrder(int $stock = 5, int $quantity = 2): Order
{
    $variant = Product::factory()->withVariant(stock: $stock)->create()->variants->first();
    fillCart(signIn(), [[$variant, $quantity]]);
    checkout()->assertCreated();

    return Order::query()->latest('id')->firstOrFail();
}

it('shows customers only their own orders', function () {
    $order = placedOrder();

    $this->getJson("/api/v1/orders/{$order->id}")->assertOk()->assertJsonPath('data.id', $order->id);
    $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data');

    signIn();
    $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
    $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
});

it('lets admins view any order', function () {
    $order = placedOrder();

    signIn(User::factory()->admin()->create());

    $this->getJson("/api/v1/orders/{$order->id}")->assertOk();
});

it('cancels an unpaid order and puts the stock back', function () {
    $order = placedOrder(stock: 5, quantity: 2);
    $variant = $order->items()->firstOrFail()->variant;
    expect($variant->stock)->toBe(3);

    $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($variant->fresh()->stock)->toBe(5)
        ->and($order->vendorOrders()->first()->status->value)->toBe('cancelled');
});

it('cannot cancel a paid order', function () {
    $order = placedOrder();
    $order->markAsPaid();

    $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_state_transition');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('lets a vendor see and ship only their own paid sub-orders', function () {
    $order = placedOrder();
    $vendorOrder = $order->vendorOrders()->with('vendor.user')->firstOrFail();
    $vendorUser = $vendorOrder->vendor->user;

    signIn($vendorUser);
    $this->getJson('/api/v1/vendor/orders')->assertOk()->assertJsonCount(1, 'data');
    $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship")->assertStatus(409);

    $order->markAsPaid();

    $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship")
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped');

    $other = placedOrder();
    $otherVendorOrder = VendorOrder::query()->where('order_id', $other->id)->firstOrFail();

    signIn($vendorUser);
    $this->postJson("/api/v1/vendor/orders/{$otherVendorOrder->id}/ship")->assertForbidden();
});

it('returns 404 in the standard format for unknown orders', function () {
    signIn();

    $this->getJson('/api/v1/orders/999999')
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'not_found', 'message' => 'Not Found.', 'details' => null]]);
});
