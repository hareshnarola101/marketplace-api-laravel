<?php

namespace App\Enums;

enum VendorOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PendingPayment => in_array($next, [self::Paid, self::Cancelled], true),
            self::Paid => $next === self::Shipped,
            self::Shipped, self::Cancelled => false,
        };
    }
}
