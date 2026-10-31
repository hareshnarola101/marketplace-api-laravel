<?php

namespace App\Payments;

use App\Exceptions\InvalidWebhookSignature;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * In-memory gateway for tests and local development. Webhooks are signed with
 * a plain HMAC of the body so they can be produced without the Stripe CLI.
 */
final class FakeGateway implements PaymentGateway
{
    /** @var array<string, PaymentIntent> keyed by idempotency key */
    private array $intentsByKey = [];

    /** @var array<string, PaymentIntent> keyed by intent id */
    private array $intents = [];

    public function __construct(private readonly string $webhookSecret) {}

    public function createPaymentIntent(Money $amount, string $idempotencyKey, array $metadata = []): PaymentIntent
    {
        if (isset($this->intentsByKey[$idempotencyKey])) {
            return $this->intentsByKey[$idempotencyKey];
        }

        $id = 'pi_fake_'.Str::random(24);
        $intent = new PaymentIntent($id, $id.'_secret_'.Str::random(16), 'requires_payment_method');

        return $this->intents[$id] = $this->intentsByKey[$idempotencyKey] = $intent;
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->intents[$id] ?? new PaymentIntent($id, $id.'_secret_restored', 'requires_payment_method');
    }

    public function cancelPaymentIntent(string $id): void
    {
        $this->intents[$id] = new PaymentIntent($id, $this->retrievePaymentIntent($id)->clientSecret, 'canceled');
    }

    public function parseWebhook(string $payload, ?string $signature): PaymentEvent
    {
        if ($signature === null || ! hash_equals($this->sign($payload), $signature)) {
            throw new InvalidWebhookSignature;
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new InvalidWebhookSignature;
        }

        return PaymentEvent::fromStripePayload($event);
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret);
    }
}
