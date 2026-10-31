<?php

namespace App\Http\Controllers;

use App\Actions\Cart\AddToCart;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $items = $user->cartItems()->with('variant.product.vendor')->oldest('id')->get();

        $total = $items->reduce(
            fn (Money $sum, CartItem $item) => $sum->plus($item->variant->priceMoney()->times($item->quantity)),
            Money::zero(config()->string('marketplace.currency')),
        );

        return CartItemResource::collection($items)
            ->additional(['meta' => ['total' => $total, 'vendor_count' => $items->pluck('variant.product.vendor_id')->unique()->count()]])
            ->response();
    }

    public function store(AddCartItemRequest $request, AddToCart $addToCart): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $variant = ProductVariant::query()->findOrFail($request->integer('variant_id'));

        $item = $addToCart->handle($user, $variant, $request->integer('quantity'));

        return (new CartItemResource($item->load('variant.product.vendor')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): CartItemResource
    {
        Gate::authorize('update', $cartItem);

        $cartItem->load('variant.product.vendor');
        $cartItem->variant->ensureCanBeBought($request->integer('quantity'));
        $cartItem->update(['quantity' => $request->integer('quantity')]);

        return new CartItemResource($cartItem);
    }

    public function destroy(CartItem $cartItem): Response
    {
        Gate::authorize('delete', $cartItem);

        $cartItem->delete();

        return response()->noContent();
    }
}
