<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'status'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
        ];
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Active, not deleted, and sold by a vendor who is currently approved. */
    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Active
            && ! $this->trashed()
            && $this->vendor?->isApproved() === true;
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereHas('vendor', fn (Builder $vendor) => $vendor->approved());
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('description', 'like', $like));
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function soldBy(Builder $query, string $vendorSlug): void
    {
        $query->whereHas('vendor', fn (Builder $vendor) => $vendor->where('slug', $vendorSlug));
    }

    /**
     * Products with at least one variant inside the price range (cents).
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function priceBetween(Builder $query, ?int $min, ?int $max): void
    {
        $query->whereHas('variants', fn (Builder $variant) => $variant
            ->when($min !== null, fn (Builder $q) => $q->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('price', '<=', $max)));
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function inStock(Builder $query): void
    {
        $query->whereHas('variants', fn (Builder $variant) => $variant->where('stock', '>', 0));
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function sortBy(Builder $query, string $sort): void
    {
        match ($sort) {
            'price' => $query->orderBy('variants_min_price')->orderBy('id'),
            '-price' => $query->orderByDesc('variants_min_price')->orderBy('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }
}
