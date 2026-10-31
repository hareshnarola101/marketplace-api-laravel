<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVariantRequest;
use App\Http\Requests\Vendor\UpdateVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VariantController extends Controller
{
    public function store(StoreVariantRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        return (new ProductVariantResource($product->variants()->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateVariantRequest $request, ProductVariant $variant): ProductVariantResource
    {
        Gate::authorize('update', $variant->product()->withTrashed()->firstOrFail());

        $variant->update($request->validated());

        return new ProductVariantResource($variant);
    }
}
