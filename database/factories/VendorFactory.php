<?php

namespace Database\Factories;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'store_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'status' => VendorStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->vendor(),
            'status' => VendorStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => VendorStatus::Rejected,
            'rejection_reason' => 'Incomplete business details.',
            'reviewed_at' => now(),
        ]);
    }

    public function withCommission(int $rateBps): static
    {
        return $this->state(['commission_rate_bps' => $rateBps]);
    }
}
