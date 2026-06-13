<?php

namespace App\Services\Payments;

use App\Exceptions\WebhookVerificationException;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Unified payment abstraction. Booking services never call
 * provider-specific APIs directly.
 */
interface PaymentProvider
{
    public function name(): string;

    /**
     * Create one checkout covering one or more bookings (slots booked
     * together). Charges the sum of their deposit amounts and returns a
     * pending Payment, linked to the first booking, with a checkout URL.
     *
     * @param  Collection<int, Booking>  $bookings
     */
    public function createCheckout(Collection $bookings, string $successUrl, string $cancelUrl): Payment;

    /**
     * Verify a webhook request's authenticity and parse it. Implementations
     * MUST reject requests with invalid signatures.
     *
     * @throws WebhookVerificationException
     */
    public function verifyWebhook(Request $request): WebhookEvent;
}
