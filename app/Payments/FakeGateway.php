<?php

namespace App\Payments;

use App\Exceptions\InvalidWebhookSignature;
use App\Support\Money;

/**
 * Offline gateway for tests and local development. Intent ids are derived
 * from the idempotency key, so retries return the same intent the way Stripe
 * does. Webhooks are signed with a plain HMAC of the body.
 */
final class FakeGateway implements PaymentGateway
{
    /** @var array<string, string> intent id => status */
    private array $statuses = [];

    public function __construct(private readonly string $webhookSecret) {}

    public function createPaymentIntent(Money $amount, string $idempotencyKey, array $metadata = []): PaymentIntent
    {
        return $this->retrievePaymentIntent('pi_fake_'.substr(hash('sha256', $idempotencyKey), 0, 24));
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return new PaymentIntent(
            $id,
            $id.'_secret_'.substr(hash_hmac('sha256', $id, $this->webhookSecret), 0, 16),
            $this->statuses[$id] ?? 'requires_payment_method',
        );
    }

    public function cancelPaymentIntent(string $id): void
    {
        $this->statuses[$id] = 'canceled';
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
