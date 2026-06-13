<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\WebhookVerificationException;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * PayMongo checkout sessions (GCash, Maya, cards) for the Philippine market.
 *
 * @see https://developers.paymongo.com/docs
 */
class PayMongoProvider implements PaymentProvider
{
    private const BASE_URL = 'https://api.paymongo.com/v1';

    public function name(): string
    {
        return 'paymongo';
    }

    public function createCheckout(Collection $bookings, string $successUrl, string $cancelUrl): Payment
    {
        $booking = $bookings->first();
        $charge = $bookings->sum(fn (Booking $b) => $b->depositAmount());

        $lineItems = $bookings->map(fn (Booking $b) => [
            'name' => $b->balanceDue() > 0
                ? $b->resource->name.' — deposit ('.$b->starts_at->toDayDateTimeString().')'
                : $b->resource->name.' — '.$b->starts_at->toDayDateTimeString(),
            'amount' => $b->depositAmount(),
            'currency' => $b->currency,
            'quantity' => 1,
        ])->values()->all();

        $response = Http::withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 250, throw: false)
            ->post(self::BASE_URL.'/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'line_items' => $lineItems,
                        'payment_method_types' => ['gcash', 'paymaya', 'card'],
                        'reference_number' => $booking->reference,
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'description' => $bookings->count() > 1
                            ? $bookings->count().' court bookings'
                            : 'Court booking '.$booking->reference,
                    ],
                ],
            ])
            ->throw();

        return $booking->payments()->create([
            'tenant_id' => $booking->tenant_id,
            'provider' => $this->name(),
            'provider_id' => $response->json('data.id'),
            'status' => PaymentStatus::Pending,
            'amount' => $charge,
            'currency' => $booking->currency,
            'checkout_url' => $response->json('data.attributes.checkout_url'),
        ]);
    }

    public function verifyWebhook(Request $request): WebhookEvent
    {
        $parts = collect(explode(',', (string) $request->header('Paymongo-Signature')))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

                return [trim($key) => trim($value)];
            });

        $timestamp = $parts->get('t', '');
        $signature = config('services.paymongo.livemode', false) ? $parts->get('li', '') : $parts->get('te', '');
        $expected = hash_hmac(
            'sha256',
            $timestamp.'.'.$request->getContent(),
            (string) config('services.paymongo.webhook_secret')
        );

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new WebhookVerificationException('Invalid PayMongo webhook signature.');
        }

        $payload = $request->json()->all();
        $eventType = data_get($payload, 'data.attributes.type', '');

        return new WebhookEvent(
            provider: $this->name(),
            eventId: data_get($payload, 'data.id', ''),
            type: $this->normalizeType($eventType),
            providerPaymentId: data_get($payload, 'data.attributes.data.id'),
            payload: $payload,
        );
    }

    private function normalizeType(string $providerType): string
    {
        return match ($providerType) {
            'checkout_session.payment.paid', 'payment.paid' => 'payment.paid',
            'payment.failed' => 'payment.failed',
            default => $providerType,
        };
    }
}
