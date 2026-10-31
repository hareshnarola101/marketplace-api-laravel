<?php

namespace App\Models;

use App\Exceptions\InsufficientStock;
use App\Exceptions\ProductUnavailable;
use App\Support\Money;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku', 'name', 'price', 'stock'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @throws ProductUnavailable
     * @throws InsufficientStock
     */
    public function ensureCanBeBought(int $quantity): void
    {
        if ($this->product?->isPurchasable() !== true) {
            throw new ProductUnavailable($this->sku);
        }

        if ($this->stock < $quantity) {
            throw new InsufficientStock($this->sku, $quantity, $this->stock);
        }
    }

    public function priceMoney(): Money
    {
        return Money::of($this->price, config()->string('marketplace.currency'));
    }
}
