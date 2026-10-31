<?php

use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Models\Product;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Stripe\StripeClient;

const WEBHOOK_SECRET = 'whsec_test_secret';

beforeEach(function () {
    $variant = Product::factory()->withVariant(price: 2500)->create()->variants->first();
    fillCart(signIn(), [[$variant, 1]]);
    checkout()->assertCreated();

    $this->order = Order::query()->sole();

    // From here on, exercise the real Stripe signature verification. No network calls are made.
    app()->instance(PaymentGateway::class, new StripeGateway(new StripeClient('sk_test_dummy'), WEBHOOK_SECRET));
});

function sendWebhook(string $eventId, Order $order, ?int $amount = null, string $type = 'payment_intent.succeeded', ?string $signature = null): TestResponse
{
    $payload = json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => [
            'id' => $order->payment_intent_id,
            'object' => 'payment_intent',
            'amount' => $amount ?? $order->total,
            'amount_received' => $amount ?? $order->total,
            'currency' => strtolower($order->currency),
        ]],
    ], JSON_THROW_ON_ERROR);

    return test()->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $signature ?? stripeSignature($payload, WEBHOOK_SECRET),
    ], $payload);
}

it('rejects webhooks with an invalid signature', function () {
    $order = $this->order;

    sendWebhook('evt_1', $order, signature: 't='.time().',v1=forged')
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'invalid_signature');

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('rejects webhooks signed too long ago', function () {
    $order = $this->order;
    $payload = '{"id":"evt_old"}';

    sendWebhook('evt_old', $order, signature: stripeSignature($payload, WEBHOOK_SECRET, time() - 3600))->assertStatus(400);
});

it('marks the order and its vendor orders paid', function () {
    Event::fake([OrderPaid::class]);
    $order = $this->order;

    sendWebhook('evt_paid', $order)->assertOk()->assertJson(['received' => true]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->vendorOrders->pluck('status')->unique()->all())->toBe([VendorOrderStatus::Paid]);

    Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->order->is($order));
});

it('handles the same event delivered twice only once', function () {
    Event::fake([OrderPaid::class]);
    $order = $this->order;

    sendWebhook('evt_dup', $order)->assertOk();
    sendWebhook('evt_dup', $order)->assertOk();

    Event::assertDispatchedTimes(OrderPaid::class, 1);
    expect(ProcessedWebhookEvent::query()->count())->toBe(1);
});

it('does not pay an order twice when a different event repeats the success', function () {
    Event::fake([OrderPaid::class]);
    $order = $this->order;

    sendWebhook('evt_a', $order)->assertOk();
    sendWebhook('evt_b', $order)->assertOk();

    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('does not mark the order paid when the amount does not match', function () {
    Event::fake([OrderPaid::class]);
    $order = $this->order;

    sendWebhook('evt_short', $order, amount: 100)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    Event::assertNotDispatched(OrderPaid::class);
});

it('acknowledges events it does not act on', function () {
    $order = $this->order;

    sendWebhook('evt_other', $order, type: 'payment_intent.created')->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});
