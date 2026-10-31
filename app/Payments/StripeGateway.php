<?php

namespace App\Payments;

use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\PaymentGatewayError;
use App\Support\Money;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

final class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly string $webhookSecret,
    ) {}

    public function createPaymentIntent(Money $amount, string $idempotencyKey, array $metadata = []): PaymentIntent
    {
        return $this->call(fn () => $this->stripe->paymentIntents->create([
            'amount' => $amount->amount,
            'currency' => strtolower($amount->currency),
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ], ['idempotency_key' => $idempotencyKey]));
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->call(fn () => $this->stripe->paymentIntents->retrieve($id));
    }

    public function cancelPaymentIntent(string $id): void
    {
        $this->call(fn () => $this->stripe->paymentIntents->cancel($id));
    }

    public function parseWebhook(string $payload, ?string $signature): PaymentEvent
    {
        try {
            $event = Webhook::constructEvent($payload, $signature ?? '', $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            throw new InvalidWebhookSignature;
        }

        return PaymentEvent::fromStripePayload($event->toArray());
    }

    /** @param callable(): StripePaymentIntent $request */
    private function call(callable $request): PaymentIntent
    {
        try {
            $intent = $request();
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayError('Payment provider error: '.$e->getMessage(), previous: $e);
        }

        return new PaymentIntent($intent->id, (string) $intent->client_secret, $intent->status);
    }
}
