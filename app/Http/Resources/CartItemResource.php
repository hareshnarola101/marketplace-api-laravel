<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'variant' => new ProductVariantResource($this->variant),
            'product' => [
                'name' => $this->variant->product->name,
                'slug' => $this->variant->product->slug,
                'vendor' => $this->variant->product->vendor->store_name,
            ],
            'line_total' => $this->variant->priceMoney()->times($this->quantity),
        ];
    }
}
