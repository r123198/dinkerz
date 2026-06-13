<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Facility;
use App\Models\PaymentEvent;
use App\Models\Resource;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Payments\FakePaymentProvider;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create();
    $facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $facility->id,
    ]);

    $slot = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');
    $booking = app(BookingService::class)
        ->createPendingBooking($this->court, $slot, guestEmail: 'player@example.com');

    $this->provider = app(FakePaymentProvider::class);
    $this->payment = $this->provider->createCheckout(collect([$booking]), 'https://success.test', 'https://cancel.test');
});

function postFakeWebhook(array $payload, ?string $signature = null)
{
    $body = json_encode($payload);
    $signature ??= test()->provider->sign($body);

    return test()->call(
        'POST',
        route('webhooks.payments', 'fake'),
        server: [
            'HTTP_'.str_replace('-', '_', strtoupper(FakePaymentProvider::SIGNATURE_HEADER)) => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $body,
    );
}

test('checkout creates a pending payment with a checkout url', function () {
    expect($this->payment->status)->toBe(PaymentStatus::Pending)
        ->and($this->payment->checkout_url)->toContain('/payments/'.$this->payment->id.'/simulate')
        ->and($this->payment->amount)->toBe($this->court->price_per_slot);
});

test('a verified paid webhook confirms the booking', function () {
    postFakeWebhook([
        'id' => 'evt_1',
        'type' => 'payment.paid',
        'payment_id' => $this->payment->provider_id,
    ])->assertOk();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->paid_at)->not->toBeNull()
        ->and($this->payment->booking->status)->toBe(BookingStatus::Confirmed);
});

test('duplicate webhook events are processed exactly once', function () {
    foreach (range(1, 3) as $attempt) {
        postFakeWebhook([
            'id' => 'evt_1',
            'type' => 'payment.paid',
            'payment_id' => $this->payment->provider_id,
        ])->assertOk();
    }

    expect(PaymentEvent::count())->toBe(1)
        ->and($this->payment->refresh()->booking->events()->count())->toBe(2); // created + confirmed
});

test('a failed payment releases the held inventory', function () {
    postFakeWebhook([
        'id' => 'evt_2',
        'type' => 'payment.failed',
        'payment_id' => $this->payment->provider_id,
    ])->assertOk();

    $booking = $this->payment->refresh()->booking;

    expect($this->payment->status)->toBe(PaymentStatus::Failed)
        ->and($booking->status)->toBe(BookingStatus::Failed)
        ->and(app(AvailabilityService::class)->isSlotAvailable(
            $this->court,
            $booking->starts_at->toImmutable()->setTimezone('Asia/Manila')
        ))->toBeTrue();
});

test('webhooks with invalid signatures are rejected', function () {
    postFakeWebhook(
        ['id' => 'evt_3', 'type' => 'payment.paid', 'payment_id' => $this->payment->provider_id],
        signature: 'forged'
    )->assertForbidden();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

test('unknown providers return 404', function () {
    $this->postJson(route('webhooks.payments', 'stripe-classic'), [])->assertNotFound();
});

test('webhooks for unknown payments are absorbed without error', function () {
    postFakeWebhook([
        'id' => 'evt_4',
        'type' => 'payment.paid',
        'payment_id' => 'fake_nonexistent',
    ])->assertOk();

    expect(PaymentEvent::count())->toBe(1)
        ->and(PaymentEvent::first()->payment_id)->toBeNull();
});
