<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data. Every account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create(['name' => 'Platform Admin', 'email' => 'admin@example.com']);
        User::factory()->create(['name' => 'Demo Customer', 'email' => 'customer@example.com']);

        $stores = [
            ['vendor1@example.com', 'Northwind Outfitters', null],
            ['vendor2@example.com', 'Blue Harbor Ceramics', 1500],
            ['vendor3@example.com', 'Copper Kettle Coffee', 800],
        ];

        foreach ($stores as [$email, $storeName, $commission]) {
            $vendor = Vendor::factory()->approved()->create([
                'user_id' => User::factory()->vendor()->create(['email' => $email]),
                'store_name' => $storeName,
                'slug' => Str::slug($storeName),
                'commission_rate_bps' => $commission,
            ]);

            Product::factory()
                ->count(4)
                ->for($vendor)
                ->has(ProductVariant::factory()->count(3), 'variants')
                ->create();

            Product::factory()->draft()->for($vendor)->has(ProductVariant::factory(), 'variants')->create();
        }

        Vendor::factory()->create([
            'user_id' => User::factory()->create(['email' => 'applicant@example.com']),
            'store_name' => 'Pending Paper Co.',
            'slug' => 'pending-paper-co',
        ]);

        $this->command->info('Seeded '.Product::query()->where('status', ProductStatus::Active)->count().' active products.');
    }
}
