<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;

final class ApplyAsVendor
{
    /** @param array{store_name: string, description?: string|null} $data */
    public function handle(User $user, array $data): Vendor
    {
        return $user->vendor()->create([
            'store_name' => $data['store_name'],
            'slug' => $this->uniqueSlug($data['store_name']),
            'description' => $data['description'] ?? null,
            'status' => VendorStatus::Pending,
        ]);
    }

    private function uniqueSlug(string $storeName): string
    {
        $base = Str::slug($storeName) ?: 'store';
        $slug = $base;

        while (Vendor::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
