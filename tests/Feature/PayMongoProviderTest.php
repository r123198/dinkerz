<?php

use App\Enums\PaymentStatus;
use App\Exceptions\WebhookVerificationException;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Tenant;
use App\Services\Payments\PayMongoProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paymongo.secret_key', 'sk_test_secret');
    config()->set('services.paymongo.webhook_secret', 'whsk_test_secret');
    config()->set('services.paymongo.livemode', false);

    $this->provider = new PayMongoProvider;
});

function paymongoRequest(array $payload, ?string $signature = null): Request
{
    $body = json_encode($payload);
    $timestamp = '1750000000';
    $signature ??= 't='.$timestamp.',te='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsk_test_secret').',li=';

    return Request::create(
        '/webhooks/payments/paymongo',
        'POST',
        server: ['HTTP_PAYMONGO_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        content: $body,
    );
}

test('creates a checkout session through the PayMongo API', function () {
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_123',
                'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_123'],
            ],
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $facility = Facility::factory()->create(['tenant_id' => $tenant->id]);
    $court = Resource::factory()->create(['tenant_id' => $tenant->id, 'facility_id' => $facility->id]);
    $booking = Booking::factory()->pendingPayment()->create([
        'tenant_id' => $tenant->id,
        'resource_id' => $court->id,
    ]);

    $payment = $this->provider->createCheckout(collect([$booking]), 'https://ok.test', 'https://no.test');

    expect($payment->provider)->toBe('paymongo')
        ->and($payment->provider_id)->toBe('cs_test_123')
        ->and($payment->checkout_url)->toBe('https://checkout.paymongo.com/cs_test_123')
        ->and($payment->status)->toBe(PaymentStatus::Pending);

    Http::assertSent(function ($request) use ($booking) {
        return $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && $request['data']['attributes']['reference_number'] === $booking->reference
            && $request['data']['attributes']['line_items'][0]['amount'] === $booking->amount
            && in_array('gcash', $request['data']['attributes']['payment_method_types']);
    });
});

test('verifies a valid webhook signature and normalizes the event', function () {
    $payload = [
        'data' => [
            'id' => 'evt_abc',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => ['id' => 'cs_test_123'],
            ],
        ],
    ];

    $event = $this->provider->verifyWebhook(paymongoRequest($payload));

    expect($event->eventId)->toBe('evt_abc')
        ->and($event->type)->toBe('payment.paid')
        ->and($event->isPaid())->toBeTrue()
        ->and($event->providerPaymentId)->toBe('cs_test_123');
});

test('rejects webhooks with a tampered signature', function () {
    $payload = ['data' => ['id' => 'evt_abc', 'attributes' => ['type' => 'payment.paid', 'data' => ['id' => 'cs_1']]]];

    $this->provider->verifyWebhook(paymongoRequest($payload, signature: 't=1,te=tampered,li='));
})->throws(WebhookVerificationException::class);

test('rejects webhooks with a missing signature header', function () {
    $request = Request::create('/webhooks/payments/paymongo', 'POST', content: '{}');

    $this->provider->verifyWebhook($request);
})->throws(WebhookVerificationException::class);
