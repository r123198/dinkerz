<?php

namespace App\Services\Payments;

/**
 * Provider-agnostic representation of a verified payment webhook event.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public string $provider,
        public string $eventId,
        public string $type,
        public ?string $providerPaymentId,
        public array $payload = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->type === 'payment.paid';
    }

    public function isFailed(): bool
    {
        return $this->type === 'payment.failed';
    }
}
