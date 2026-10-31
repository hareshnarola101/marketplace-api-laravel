<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PendingPayment => in_array($next, [self::Paid, self::Cancelled], true),
            self::Paid, self::Cancelled => false,
        };
    }
}
