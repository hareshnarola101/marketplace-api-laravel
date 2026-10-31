<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Vendor;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product:slug}', [ProductController::class, 'show']);

// Stripe retries from many IPs; the signature check is the gate, not the rate limiter.
Route::post('webhooks/stripe', StripeWebhookController::class)->withoutMiddleware('throttle:api');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'store']);
    Route::patch('cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);

    Route::post('checkout', CheckoutController::class);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::prefix('vendor')->group(function () {
        Route::get('application', [Vendor\ApplicationController::class, 'show']);
        Route::post('application', [Vendor\ApplicationController::class, 'store']);

        Route::middleware('can:act-as-vendor')->group(function () {
            Route::apiResource('products', Vendor\ProductController::class);
            Route::post('products/{product}/variants', [Vendor\VariantController::class, 'store']);
            Route::patch('variants/{variant}', [Vendor\VariantController::class, 'update']);

            Route::get('orders', [Vendor\OrderController::class, 'index']);
            Route::post('orders/{vendorOrder}/ship', [Vendor\OrderController::class, 'ship']);

            Route::get('payouts/summary', Vendor\PayoutSummaryController::class);
        });
    });

    Route::prefix('admin')->middleware('can:access-admin')->group(function () {
        Route::get('vendors', [Admin\VendorController::class, 'index']);
        Route::post('vendors/{vendor}/approve', [Admin\VendorController::class, 'approve']);
        Route::post('vendors/{vendor}/reject', [Admin\VendorController::class, 'reject']);
        Route::patch('vendors/{vendor}/commission', [Admin\VendorController::class, 'updateCommission']);
        Route::post('vendors/{vendor}/payouts', [Admin\PayoutController::class, 'store']);
    });
});
