<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\Products\CreateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreProductRequest;
use App\Http\Requests\Vendor\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $this->vendor($request)->products()->with('variants')->latest('id')->paginate(15),
        );
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        Gate::authorize('create', Product::class);

        /** @var array{name: string, description?: string|null, status: string, variants: list<array{sku: string, name: string, price: int, stock: int}>} $data */
        $data = $request->productData();

        return (new ProductResource($createProduct->handle($this->vendor($request), $data)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return new ProductResource($product->load('variants'));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        $product->update($request->validated());

        return new ProductResource($product->load('variants'));
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }

    private function vendor(Request $request): Vendor
    {
        /** @var User $user */
        $user = $request->user();

        return $user->approvedVendorOrFail();
    }
}
