<?php

namespace App\Actions\Vendors;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

final class ReviewVendorApplication
{
    public function approve(Vendor $vendor, User $admin): Vendor
    {
        return $this->review($vendor, $admin, VendorStatus::Approved);
    }

    public function reject(Vendor $vendor, User $admin, string $reason): Vendor
    {
        return $this->review($vendor, $admin, VendorStatus::Rejected, $reason);
    }

    private function review(Vendor $vendor, User $admin, VendorStatus $decision, ?string $reason = null): Vendor
    {
        return DB::transaction(function () use ($vendor, $admin, $decision, $reason) {
            // Lock so two admins clicking at the same time can't both decide.
            $vendor = Vendor::query()->lockForUpdate()->findOrFail($vendor->id);

            if (! $vendor->status->canTransitionTo($decision)) {
                throw InvalidStateTransition::between('vendor application', $vendor->status->value, $decision->value);
            }

            $vendor->update([
                'status' => $decision,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($decision === VendorStatus::Approved) {
                $vendor->user()->update(['role' => UserRole::Vendor]);
            }

            return $vendor;
        });
    }
}
