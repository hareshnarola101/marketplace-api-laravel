<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    public function __construct(OrderItem $resource, private readonly ?string $currency = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $currency = $this->currency ?? config()->string('marketplace.currency');

        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'variant_name' => $this->variant_name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => Money::of($this->unit_price, $currency),
            'line_total' => Money::of($this->line_total, $currency),
        ];
    }
}
