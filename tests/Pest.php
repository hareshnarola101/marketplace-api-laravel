<?php

use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function signIn(?User $user = null): User
{
    $user ??= User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

/** @param array<int, array{0: ProductVariant, 1: int}> $lines */
function fillCart(User $user, array $lines): void
{
    foreach ($lines as [$variant, $quantity]) {
        CartItem::query()->forceCreate([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
    }
}

function checkout(?string $idempotencyKey = null): TestResponse
{
    return test()->postJson('/api/v1/checkout', [], [
        'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
    ]);
}

/** Build the Stripe-Signature header the same way Stripe does (HMAC-SHA256 over "timestamp.payload"). */
function stripeSignature(string $payload, string $secret, ?int $timestamp = null): string
{
    $timestamp ??= time();

    return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
}
