<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::Pending && $next !== self::Pending;
    }
}
