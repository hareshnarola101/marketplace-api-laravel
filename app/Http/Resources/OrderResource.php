<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->totalMoney(),
            'vendor_orders' => VendorOrderResource::collection($this->whenLoaded('vendorOrders')),
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->number),
            'paid_at' => $this->paid_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}
