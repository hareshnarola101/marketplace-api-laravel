<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\InvalidStateTransition;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['status', 'currency', 'total', 'idempotency_key', 'payment_intent_id', 'paid_at', 'cancelled_at'])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'integer',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<VendorOrder, $this> */
    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class)->chaperone('order');
    }

    /** @return HasManyThrough<OrderItem, VendorOrder, $this> */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, VendorOrder::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function totalMoney(): Money
    {
        return Money::of($this->total, $this->currency);
    }

    public function markAsPaid(): void
    {
        $this->transitionTo(OrderStatus::Paid, VendorOrderStatus::Paid, ['paid_at' => now()]);
    }

    public function markAsCancelled(): void
    {
        $this->transitionTo(OrderStatus::Cancelled, VendorOrderStatus::Cancelled, ['cancelled_at' => now()]);
    }

    /** @param array<string, mixed> $attributes */
    private function transitionTo(OrderStatus $status, VendorOrderStatus $vendorStatus, array $attributes): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidStateTransition::between('order', $this->status->value, $status->value);
        }

        $this->update(['status' => $status, ...$attributes]);
        $this->vendorOrders()->update(['status' => $vendorStatus]);
    }
}
