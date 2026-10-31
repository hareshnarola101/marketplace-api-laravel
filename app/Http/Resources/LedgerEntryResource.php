<?php

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => Money::of($this->amount, $this->currency),
            'vendor_order_id' => $this->vendor_order_id,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
