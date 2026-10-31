<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Access\Response;

class VendorPolicy
{
    public function create(User $user): Response
    {
        if ($user->role !== UserRole::Customer) {
            return Response::deny('Only customer accounts can apply to become a vendor.');
        }

        return $user->vendor()->exists()
            ? Response::deny('You have already applied.')
            : Response::allow();
    }

    public function review(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin();
    }
}
