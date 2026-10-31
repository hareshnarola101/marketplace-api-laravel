<?php

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;

it('lets a customer apply to become a vendor', function () {
    $user = signIn();

    $this->postJson('/api/v1/vendor/application', ['store_name' => 'Acme Goods', 'description' => 'Hand-made tools'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.slug', 'acme-goods');

    expect($user->fresh()->vendor->status)->toBe(VendorStatus::Pending)
        ->and($user->fresh()->role)->toBe(UserRole::Customer);
});

it('does not allow applying twice', function () {
    $vendor = Vendor::factory()->create();
    signIn($vendor->user);

    $this->postJson('/api/v1/vendor/application', ['store_name' => 'Second Store'])
        ->assertForbidden()
        ->assertJsonPath('error.message', 'You have already applied.');
});

it('lets an admin approve a pending application', function () {
    $vendor = Vendor::factory()->create();
    $admin = signIn(User::factory()->admin()->create());

    $this->postJson("/api/v1/admin/vendors/{$vendor->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $vendor->refresh();
    expect($vendor->status)->toBe(VendorStatus::Approved)
        ->and($vendor->reviewed_by)->toBe($admin->id)
        ->and($vendor->user->role)->toBe(UserRole::Vendor);
});

it('lets an admin reject an application with a reason', function () {
    $vendor = Vendor::factory()->create();
    signIn(User::factory()->admin()->create());

    $this->postJson("/api/v1/admin/vendors/{$vendor->id}/reject", ['reason' => 'Missing tax id'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Missing tax id');

    expect($vendor->user->fresh()->role)->toBe(UserRole::Customer);
});

it('only reviews pending applications', function () {
    $vendor = Vendor::factory()->rejected()->create();
    signIn(User::factory()->admin()->create());

    $this->postJson("/api/v1/admin/vendors/{$vendor->id}/approve")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_state_transition');
});

it('forbids non-admins from reviewing applications', function () {
    $vendor = Vendor::factory()->create();
    signIn(Vendor::factory()->approved()->create()->user);

    $this->postJson("/api/v1/admin/vendors/{$vendor->id}/approve")->assertForbidden();
    $this->getJson('/api/v1/admin/vendors')->assertForbidden();
});

it('lists applications filtered by status for admins', function () {
    Vendor::factory()->count(2)->create();
    Vendor::factory()->approved()->create();
    signIn(User::factory()->admin()->create());

    $this->getJson('/api/v1/admin/vendors?status=pending')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('blocks pending vendors from listing products until approved', function () {
    $vendor = Vendor::factory()->create();
    signIn($vendor->user);

    $product = [
        'name' => 'Steel Hammer',
        'status' => 'active',
        'variants' => [['sku' => 'HAM-1', 'name' => 'Standard', 'price' => 2500, 'stock' => 5]],
    ];

    $this->postJson('/api/v1/vendor/products', $product)->assertForbidden();

    $vendor->update(['status' => VendorStatus::Approved]);
    signIn($vendor->user->fresh());

    $this->postJson('/api/v1/vendor/products', $product)
        ->assertCreated()
        ->assertJsonPath('data.variants.0.price.amount', 2500);
});
