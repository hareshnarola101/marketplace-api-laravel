<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => Str::upper(Str::random(10)),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large', 'Red', 'Blue', 'Standard']),
            'price' => fake()->numberBetween(500, 20000),
            'stock' => fake()->numberBetween(0, 50),
        ];
    }
}
