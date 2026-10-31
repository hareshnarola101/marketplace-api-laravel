<?php

namespace App\Http\Resources;

use App\Billing\PayoutSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property PayoutSummary $resource */
class PayoutSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'gross_sales' => $this->resource->grossSales,
            'commission' => $this->resource->commission,
            'net_earnings' => $this->resource->netEarnings,
            'paid_out' => $this->resource->paidOut,
            'balance' => $this->resource->balance,
            'commission_rate_bps' => $this->resource->commissionRateBps,
        ];
    }
}
