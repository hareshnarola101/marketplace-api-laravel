<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VendorOrder */
class VendorOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'subtotal' => $this->money($this->subtotal),
            'commission_rate_bps' => $this->commission_rate_bps,
            'commission' => $this->money($this->commission_amount),
            'vendor_earnings' => $this->money($this->vendor_earnings),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(
                fn (OrderItem $item) => new OrderItemResource($item, $this->order->currency),
            )),
            'shipped_at' => $this->shipped_at,
            'created_at' => $this->created_at,
        ];
    }
}
