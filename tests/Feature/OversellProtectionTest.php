<?php

use App\Models\Order;
use App\Models\Product;

it('refuses to sell more units than are in stock', function () {
    $variant = Product::factory()->withVariant(stock: 2)->create()->variants->first();
    $customer = signIn();
    fillCart($customer, [[$variant, 3]]);

    checkout()
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'insufficient_stock')
        ->assertJsonPath('error.details.sku', $variant->sku)
        ->assertJsonPath('error.details.available', 2);

    expect($variant->fresh()->stock)->toBe(2)
        ->and(Order::query()->count())->toBe(0)
        ->and($customer->cartItems()->count())->toBe(1);
});

it('lets only one of two customers buy the last unit', function () {
    $variant = Product::factory()->withVariant(stock: 1)->create()->variants->first();

    // Both carts were filled while the item was still in stock.
    fillCart($alice = signIn(), [[$variant, 1]]);
    fillCart($bob = signIn(), [[$variant, 1]]);

    signIn($alice);
    checkout()->assertCreated();

    signIn($bob);
    checkout()->assertStatus(409)->assertJsonPath('error.code', 'insufficient_stock');

    expect($variant->fresh()->stock)->toBe(0)
        ->and(Order::query()->count())->toBe(1);
});

it('rolls back every line when one line is short', function () {
    $plenty = Product::factory()->withVariant(stock: 50)->create()->variants->first();
    $scarce = Product::factory()->withVariant(stock: 1)->create()->variants->first();
    $customer = signIn();
    fillCart($customer, [[$plenty, 5], [$scarce, 2]]);

    checkout()->assertStatus(409);

    expect($plenty->fresh()->stock)->toBe(50)
        ->and($scarce->fresh()->stock)->toBe(1);
});
