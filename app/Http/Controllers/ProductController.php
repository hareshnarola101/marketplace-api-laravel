<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\ListProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->visible()
            ->with(['vendor', 'variants'])
            ->withMin('variants', 'price')
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('vendor'), fn ($q) => $q->soldBy($request->string('vendor')->toString()))
            ->when($request->hasAny(['min_price', 'max_price']), fn ($q) => $q->priceBetween(
                $request->filled('min_price') ? $request->integer('min_price') : null,
                $request->filled('max_price') ? $request->integer('max_price') : null,
            ))
            ->when($request->boolean('in_stock'), fn ($q) => $q->inStock())
            ->sortBy($request->string('sort', 'newest')->toString())
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless(Product::query()->visible()->whereKey($product->id)->exists(), 404);

        return new ProductResource($product->load(['vendor', 'variants']));
    }
}
