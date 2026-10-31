<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vendor */
class VendorResource extends JsonResource
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        $showPrivate = $request->user()?->isAdmin() || $request->user()?->id === $this->user_id;

        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'slug' => $this->slug,
            'description' => $this->description,
            $this->mergeWhen($showPrivate, fn () => [
                'status' => $this->status,
                'commission_rate_bps' => $this->commission_rate_bps,
                'rejection_reason' => $this->rejection_reason,
                'reviewed_at' => $this->reviewed_at,
                'user' => new UserResource($this->whenLoaded('user')),
            ]),
        ];
    }
}
