<?php

namespace App\Payments;

final readonly class PaymentEvent
{
    public const PAYMENT_SUCCEEDED = 'payment_intent.succeeded';

    public function __construct(
        public string $id,
        public string $type,
        public ?string $paymentIntentId = null,
        public ?int $amount = null,
        public ?string $currency = null,
    ) {}

    /**
     * Both gateways receive the same Stripe-shaped event body.
     *
     * @param  array<string, mixed>  $event
     */
    public static function fromStripePayload(array $event): self
    {
        $type = (string) ($event['type'] ?? '');
        /** @var array<string, mixed> $object */
        $object = $event['data']['object'] ?? [];
        $isPaymentIntent = str_starts_with($type, 'payment_intent.');

        return new self(
            id: (string) ($event['id'] ?? ''),
            type: $type,
            paymentIntentId: $isPaymentIntent ? (string) $object['id'] : null,
            amount: $isPaymentIntent ? (int) ($object['amount_received'] ?? $object['amount'] ?? 0) : null,
            currency: $isPaymentIntent ? strtoupper((string) $object['currency']) : null,
        );
    }
}
