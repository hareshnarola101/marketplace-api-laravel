<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Classic', 'Handmade', 'Organic', 'Vintage', 'Everyday', 'Premium'])
            .' '.fake()->randomElement(['Linen', 'Ceramic', 'Leather', 'Cotton', 'Walnut', 'Steel'])
            .' '.fake()->randomElement(['Mug', 'Tote Bag', 'Notebook', 'Apron', 'Candle', 'Serving Board']);

        return [
            'vendor_id' => Vendor::factory()->approved(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'status' => ProductStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft]);
    }

    /** Shortcut for the common "one product, one variant" case in tests. */
    public function withVariant(int $price = 1000, int $stock = 10): static
    {
        return $this->has(ProductVariant::factory()->state(['price' => $price, 'stock' => $stock]), 'variants');
    }
}
