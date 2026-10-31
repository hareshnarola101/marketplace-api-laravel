<?php

use App\Models\CartItem;
use App\Models\Product;

it('adds items and merges quantities for the same variant', function () {
    $variant = Product::factory()->withVariant(price: 1250, stock: 10)->create()->variants->first();
    signIn();

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertCreated();
    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 3])->assertCreated();

    $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', 5)
        ->assertJsonPath('meta.total.amount', 6250);
});

it('rejects quantities above available stock', function () {
    $variant = Product::factory()->withVariant(stock: 2)->create()->variants->first();
    signIn();

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 3])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'insufficient_stock')
        ->assertJsonPath('error.details.available', 2);
});

it('rejects products that are not for sale', function () {
    $variant = Product::factory()->draft()->withVariant()->create()->variants->first();
    signIn();

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'product_unavailable');
});

it('does not let one customer touch another customer\'s cart', function () {
    $variant = Product::factory()->withVariant()->create()->variants->first();
    $owner = signIn();
    fillCart($owner, [[$variant, 1]]);
    $item = CartItem::query()->firstOrFail();

    signIn();

    $this->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 5])->assertForbidden();
    $this->deleteJson("/api/v1/cart/items/{$item->id}")->assertForbidden();
});
