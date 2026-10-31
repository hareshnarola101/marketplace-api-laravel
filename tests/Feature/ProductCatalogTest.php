<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;

it('lists only active products from approved vendors', function () {
    $visible = Product::factory()->withVariant()->create();
    Product::factory()->draft()->withVariant()->create();
    Product::factory()->withVariant()->create()->delete();
    Product::factory()->withVariant()->for(Vendor::factory())->create();

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id);
});

it('searches by name and filters by price, vendor and stock', function () {
    $vendor = Vendor::factory()->approved()->create();
    $mug = Product::factory()->for($vendor)->withVariant(price: 1500, stock: 3)->create(['name' => 'Stoneware Mug']);
    Product::factory()->for($vendor)->withVariant(price: 9000, stock: 3)->create(['name' => 'Stoneware Vase']);
    Product::factory()->withVariant(price: 1200, stock: 0)->create(['name' => 'Paper Mug']);

    $ids = fn (string $query) => collect($this->getJson("/api/v1/products?{$query}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('search=mug'))->toHaveCount(2)
        ->and($ids('search=stoneware&max_price=2000'))->toBe([$mug->id])
        ->and($ids("vendor={$vendor->slug}"))->toHaveCount(2)
        ->and($ids('search=mug&in_stock=1'))->toBe([$mug->id]);
});

it('sorts by lowest variant price', function () {
    $cheap = Product::factory()->withVariant(price: 500)->create();
    $expensive = Product::factory()->withVariant(price: 5000)->create();

    expect(collect($this->getJson('/api/v1/products?sort=-price')->json('data'))->pluck('id')->all())
        ->toBe([$expensive->id, $cheap->id]);
});

it('validates filter input', function () {
    $this->getJson('/api/v1/products?min_price=500&max_price=100&sort=random')
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['max_price', 'sort']]]);
});

it('hides draft products on the detail endpoint', function () {
    $product = Product::factory()->draft()->create();

    $this->getJson("/api/v1/products/{$product->slug}")->assertNotFound()->assertJsonPath('error.code', 'not_found');
});

it('lets a vendor manage only their own products', function () {
    $mine = Product::factory()->withVariant()->create();
    $theirs = Product::factory()->withVariant()->create();
    signIn($mine->vendor->user);

    $this->patchJson("/api/v1/vendor/products/{$mine->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
    $this->patchJson("/api/v1/vendor/products/{$theirs->id}", ['name' => 'Hijacked'])->assertForbidden();
    $this->deleteJson("/api/v1/vendor/products/{$theirs->id}")->assertForbidden();
    $this->patchJson("/api/v1/vendor/variants/{$theirs->variants->first()->id}", ['stock' => 0])->assertForbidden();
});

it('soft deletes products so past orders keep their history', function () {
    $product = Product::factory()->withVariant()->create();
    signIn($product->vendor->user);

    $this->deleteJson("/api/v1/vendor/products/{$product->id}")->assertNoContent();

    $this->assertSoftDeleted($product);
    expect(ProductVariant::query()->where('product_id', $product->id)->exists())->toBeTrue();
});

it('lets a vendor add a variant and change stock', function () {
    $product = Product::factory()->withVariant(stock: 1)->create();
    signIn($product->vendor->user);

    $this->postJson("/api/v1/vendor/products/{$product->id}/variants", ['sku' => 'NEW-1', 'name' => 'XL', 'price' => 1999, 'stock' => 4])
        ->assertCreated()
        ->assertJsonPath('data.price.formatted', '19.99');

    $this->patchJson("/api/v1/vendor/variants/{$product->variants->first()->id}", ['stock' => 25])
        ->assertOk()
        ->assertJsonPath('data.stock', 25);
});
