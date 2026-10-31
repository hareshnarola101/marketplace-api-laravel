<?php

namespace App\Payments;

use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\PaymentGatewayError;
use App\Support\Money;

interface PaymentGateway
{
    /**
     * The idempotency key is forwarded to the provider, so calling this twice
     * for the same order returns the same intent instead of creating a second one.
     *
     * @param  array<string, string>  $metadata
     *
     * @throws PaymentGatewayError
     */
    public function createPaymentIntent(Money $amount, string $idempotencyKey, array $metadata = []): PaymentIntent;

    /** @throws PaymentGatewayError */
    public function retrievePaymentIntent(string $id): PaymentIntent;

    /** @throws PaymentGatewayError */
    public function cancelPaymentIntent(string $id): void;

    /**
     * Verify the signature and turn the raw payload into a provider-neutral event.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(string $payload, ?string $signature): PaymentEvent;
}
