<?php

use App\Actions\Payments\HandlePaymentWebhook;
use App\Enums\LedgerEntryType;
use App\Events\OrderPaid;
use App\Listeners\CreateInvoice;
use App\Listeners\NotifyVendorsOfPaidOrder;
use App\Listeners\RecordVendorEarnings;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Notifications\VendorOrderReceived;
use App\Payments\PaymentEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function paidOrder(Vendor ...$vendors): Order
{
    $customer = signIn();

    foreach ($vendors as $vendor) {
        fillCart($customer, [[Product::factory()->for($vendor)->withVariant(price: 10000)->create()->variants->first(), 1]]);
    }

    checkout()->assertCreated();
    $order = Order::query()->latest('id')->firstOrFail();

    app(HandlePaymentWebhook::class)->handle(new PaymentEvent(
        id: 'evt_'.uniqid(),
        type: PaymentEvent::PAYMENT_SUCCEEDED,
        paymentIntentId: $order->payment_intent_id,
        amount: $order->total,
        currency: $order->currency,
    ));

    return $order->refresh();
}

it('wires every listener to the OrderPaid event', function () {
    Event::fake();

    Event::assertListening(OrderPaid::class, RecordVendorEarnings::class);
    Event::assertListening(OrderPaid::class, CreateInvoice::class);
    Event::assertListening(OrderPaid::class, NotifyVendorsOfPaidOrder::class);
});

it('pushes the listeners onto the queue with retries and backoff', function () {
    Queue::fake();

    paidOrder(Vendor::factory()->approved()->create());

    Queue::assertPushed(CallQueuedListener::class, 3);
    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === RecordVendorEarnings::class
        && $job->tries === 5
        && $job->backoff === [10, 60, 300]);
});

it('books a sale and a commission entry per vendor', function () {
    $standard = Vendor::factory()->approved()->create();
    $premium = Vendor::factory()->approved()->withCommission(2000)->create();

    paidOrder($standard, $premium);

    $entries = fn (Vendor $vendor) => LedgerEntry::query()->where('vendor_id', $vendor->id)->pluck('amount', 'type')->all();

    expect($entries($standard))->toEqual([LedgerEntryType::Sale->value => 10000, LedgerEntryType::Commission->value => -1000])
        ->and($entries($premium))->toEqual([LedgerEntryType::Sale->value => 10000, LedgerEntryType::Commission->value => -2000]);
});

it('does not double-book the ledger when the listener is retried', function () {
    $order = paidOrder(Vendor::factory()->approved()->create());

    app(RecordVendorEarnings::class)->handle(new OrderPaid($order));
    app(RecordVendorEarnings::class)->handle(new OrderPaid($order));

    expect(LedgerEntry::query()->count())->toBe(2);
});

it('creates exactly one invoice per order', function () {
    $order = paidOrder(Vendor::factory()->approved()->create());

    app(CreateInvoice::class)->handle(new OrderPaid($order));

    $invoice = Invoice::query()->sole();
    expect($invoice->total)->toBe($order->total)
        ->and($invoice->number)->toBe(sprintf('INV-%s-%06d', now()->format('Y'), $order->id));
});

it('notifies each vendor in the order', function () {
    Notification::fake();
    $first = Vendor::factory()->approved()->create();
    $second = Vendor::factory()->approved()->create();

    paidOrder($first, $second);

    Notification::assertSentTo($first->user, VendorOrderReceived::class);
    Notification::assertSentTo($second->user, VendorOrderReceived::class);
    Notification::assertCount(2);
});
