<?php

namespace App\Models;

use App\Enums\VendorOrderStatus;
use App\Exceptions\InvalidStateTransition;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['vendor_id', 'status', 'subtotal', 'commission_rate_bps', 'commission_amount', 'vendor_earnings', 'shipped_at'])]
class VendorOrder extends Model
{
    protected function casts(): array
    {
        return [
            'status' => VendorOrderStatus::class,
            'subtotal' => 'integer',
            'commission_rate_bps' => 'integer',
            'commission_amount' => 'integer',
            'vendor_earnings' => 'integer',
            'shipped_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function money(int $amount): Money
    {
        return Money::of($amount, $this->order->currency);
    }

    public function markAsShipped(): void
    {
        if (! $this->status->canTransitionTo(VendorOrderStatus::Shipped)) {
            throw InvalidStateTransition::between('vendor order', $this->status->value, VendorOrderStatus::Shipped->value);
        }

        $this->update(['status' => VendorOrderStatus::Shipped, 'shipped_at' => now()]);
    }
}
