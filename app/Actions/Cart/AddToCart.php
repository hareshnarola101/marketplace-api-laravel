<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;

final class AddToCart
{
    /**
     * Stock is checked here for fast feedback, but nothing is reserved;
     * the authoritative check happens under a row lock at checkout.
     */
    public function handle(User $user, ProductVariant $variant, int $quantity): CartItem
    {
        $item = $user->cartItems()->firstOrNew(['product_variant_id' => $variant->id]);
        $newQuantity = ($item->exists ? $item->quantity : 0) + $quantity;

        $variant->ensureCanBeBought($newQuantity);

        $item->fill(['quantity' => $newQuantity])->save();

        return $item;
    }
}
