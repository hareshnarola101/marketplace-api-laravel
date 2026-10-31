<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('vendor')->group(function () {
        Route::get('application', [Vendor\ApplicationController::class, 'show']);
        Route::post('application', [Vendor\ApplicationController::class, 'store']);

        Route::middleware('can:act-as-vendor')->group(function () {
            Route::apiResource('products', Vendor\ProductController::class);
            Route::post('products/{product}/variants', [Vendor\VariantController::class, 'store']);
            Route::patch('variants/{variant}', [Vendor\VariantController::class, 'update']);
        });
    });

    Route::prefix('admin')->middleware('can:access-admin')->group(function () {
        Route::get('vendors', [Admin\VendorController::class, 'index']);
        Route::post('vendors/{vendor}/approve', [Admin\VendorController::class, 'approve']);
        Route::post('vendors/{vendor}/reject', [Admin\VendorController::class, 'reject']);
        Route::patch('vendors/{vendor}/commission', [Admin\VendorController::class, 'updateCommission']);
    });
});
