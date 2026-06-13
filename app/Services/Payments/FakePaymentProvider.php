<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\WebhookVerificationException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Local development and test provider. Checkouts resolve to an in-app
 * payment simulator page; webhooks are signed with the app key.
 */
class FakePaymentProvider implements PaymentProvider
{
    public const SIGNATURE_HEADER = 'X-Fake-Signature';

    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(Collection $bookings, string $successUrl, string $cancelUrl): Payment
    {
        $booking = $bookings->first();

        $payment = $booking->payments()->create([
            'tenant_id' => $booking->tenant_id,
            'provider' => $this->name(),
            'provider_id' => 'fake_'.Str::random(24),
            'status' => PaymentStatus::Pending,
            'amount' => $bookings->sum(fn ($b) => $b->depositAmount()),
            'currency' => $booking->currency,
        ]);

        $payment->update([
            'checkout_url' => url("/{$booking->tenant->slug}/payments/{$payment->id}/simulate"),
        ]);

        return $payment;
    }

    public function verifyWebhook(Request $request): WebhookEvent
    {
        $expected = $this->sign($request->getContent());

        if (! hash_equals($expected, (string) $request->header(self::SIGNATURE_HEADER))) {
            throw new WebhookVerificationException('Invalid fake webhook signature.');
        }

        $payload = $request->json()->all();

        return new WebhookEvent(
            provider: $this->name(),
            eventId: $payload['id'] ?? '',
            type: $payload['type'] ?? '',
            providerPaymentId: $payload['payment_id'] ?? null,
            payload: $payload,
        );
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('app.key'));
    }
}
