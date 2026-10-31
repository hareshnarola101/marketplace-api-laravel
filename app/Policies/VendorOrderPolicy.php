<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorOrder;

class VendorOrderPolicy
{
    public function ship(User $user, VendorOrder $vendorOrder): bool
    {
        return $user->approvedVendor()?->id === $vendorOrder->vendor_id;
    }
}
