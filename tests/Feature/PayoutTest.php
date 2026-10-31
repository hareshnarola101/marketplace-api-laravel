<?php

use App\Actions\Payments\HandlePaymentWebhook;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Payments\PaymentEvent;

function sellFor(Vendor $vendor, int $price): void
{
    fillCart(signIn(), [[Product::factory()->for($vendor)->withVariant(price: $price)->create()->variants->first(), 1]]);
    checkout()->assertCreated();
    $order = Order::query()->latest('id')->firstOrFail();

    app(HandlePaymentWebhook::class)->handle(new PaymentEvent(
        id: 'evt_'.uniqid(), type: PaymentEvent::PAYMENT_SUCCEEDED,
        paymentIntentId: $order->payment_intent_id, amount: $order->total, currency: $order->currency,
    ));
}

it('summarizes vendor earnings from the ledger', function () {
    $vendor = Vendor::factory()->approved()->withCommission(1250)->create();
    sellFor($vendor, 1999);   // 12.5% of 19.99 = 2.49875 -> 2.50
    sellFor($vendor, 4000);   // 12.5% of 40.00 = 5.00

    signIn($vendor->user);

    $this->getJson('/api/v1/vendor/payouts/summary')
        ->assertOk()
        ->assertJsonPath('data.gross_sales.amount', 5999)
        ->assertJsonPath('data.commission.amount', 750)
        ->assertJsonPath('data.net_earnings.amount', 5249)
        ->assertJsonPath('data.balance.amount', 5249)
        ->assertJsonPath('data.commission_rate_bps', 1250)
        ->assertJsonCount(4, 'recent_entries');
});

it('ignores orders that were never paid', function () {
    $vendor = Vendor::factory()->approved()->create();
    fillCart(signIn(), [[Product::factory()->for($vendor)->withVariant(price: 5000)->create()->variants->first(), 1]]);
    checkout()->assertCreated();

    signIn($vendor->user);

    $this->getJson('/api/v1/vendor/payouts/summary')->assertOk()->assertJsonPath('data.gross_sales.amount', 0);
});

it('lets an admin record a payout up to the available balance', function () {
    $vendor = Vendor::factory()->approved()->create();
    sellFor($vendor, 10000); // balance 90.00 at the default 10%

    signIn(User::factory()->admin()->create());

    $this->postJson("/api/v1/admin/vendors/{$vendor->id}/payouts", ['amount' => 9001])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'insufficient_balance');

    $this->postJson("/api/v1/admin/vendors/{$vendor->id}/payouts", ['amount' => 6000])
        ->assertCreated()
        ->assertJsonPath('data.amount.amount', -6000);

    signIn($vendor->user);
    $this->getJson('/api/v1/vendor/payouts/summary')
        ->assertJsonPath('data.paid_out.amount', 6000)
        ->assertJsonPath('data.balance.amount', 3000);
});

it('lets an admin change a vendor commission rate for future orders only', function () {
    $vendor = Vendor::factory()->approved()->create();
    sellFor($vendor, 10000);

    signIn(User::factory()->admin()->create());
    $this->patchJson("/api/v1/admin/vendors/{$vendor->id}/commission", ['commission_rate_bps' => 500])
        ->assertOk()
        ->assertJsonPath('data.commission_rate_bps', 500);

    sellFor($vendor, 10000);

    expect($vendor->vendorOrders()->orderBy('id')->pluck('commission_amount')->all())->toBe([1000, 500]);
});

it('validates the commission rate range', function () {
    $vendor = Vendor::factory()->approved()->create();
    signIn(User::factory()->admin()->create());

    $this->patchJson("/api/v1/admin/vendors/{$vendor->id}/commission", ['commission_rate_bps' => 10001])->assertStatus(422);
});

it('keeps payout summaries private to approved vendors', function () {
    signIn();
    $this->getJson('/api/v1/vendor/payouts/summary')->assertForbidden();

    signIn(Vendor::factory()->create()->user);
    $this->getJson('/api/v1/vendor/payouts/summary')->assertForbidden();
});
