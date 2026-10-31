<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateProduct
{
    /**
     * @param array{
     *     name: string,
     *     description?: string|null,
     *     status: string,
     *     variants: list<array{sku: string, name: string, price: int, stock: int}>
     * } $data
     */
    public function handle(Vendor $vendor, array $data): Product
    {
        return DB::transaction(function () use ($vendor, $data) {
            $product = $vendor->products()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);

            $product->variants()->createMany($data['variants']);

            return $product->load('variants');
        });
    }
}
