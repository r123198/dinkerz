<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Payments\FakePaymentProvider;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create(['slug' => 'ace']);
    $facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    // ₱500 court with a ₱200 online deposit; ₱300 due on-site.
    $this->court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $facility->id,
        'price_per_slot' => 50000,
        'deposit_per_slot' => 20000,
    ]);
    $this->slot = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');
});

test('a booking on a deposit court records the deposit and balance', function () {
    $booking = app(BookingService::class)
        ->createPendingBooking($this->court, $this->slot, guestEmail: 'ana@example.com');

    expect($booking->amount)->toBe(50000)
        ->and($booking->deposit_amount)->toBe(20000)
        ->and($booking->depositAmount())->toBe(20000)
        ->and($booking->balanceDue())->toBe(30000);
});

test('checkout charges only the deposit, not the full price', function () {
    $booking = app(BookingService::class)
        ->createPendingBooking($this->court, $this->slot, guestEmail: 'ana@example.com');

    $payment = app(FakePaymentProvider::class)
        ->createCheckout(collect([$booking]), 'https://ok.test', 'https://no.test');

    expect($payment->amount)->toBe(20000);
});

test('paying the deposit confirms the booking with the balance still owed', function () {
    $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ana Cruz',
        'guest_email' => 'ana@example.com',
    ]);

    $payment = Payment::sole();
    expect($payment->amount)->toBe(20000);

    $this->post("/ace/payments/{$payment->id}/simulate", ['outcome' => 'paid'])
        ->assertRedirect();

    $booking = $payment->refresh()->booking;

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->balanceDue())->toBe(30000);
});

test('courts without a deposit still charge the full price online', function () {
    $fullCourt = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->court->facility_id,
        'price_per_slot' => 50000,
        'deposit_per_slot' => null,
    ]);

    $booking = app(BookingService::class)
        ->createPendingBooking($fullCourt, $this->slot, guestEmail: 'ana@example.com');

    expect($booking->depositAmount())->toBe(50000)
        ->and($booking->balanceDue())->toBe(0);
});

test('operators can set a deposit when creating a court', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($operator)
        ->post(route('courts.store'), [
            'name' => 'Deposit Court',
            'price' => 500,
            'deposit' => 200,
            'opens_at' => '06:00',
            'closes_at' => '22:00',
            'slot_minutes' => 60,
            'buffer_minutes' => 0,
            'booking_window_days' => 30,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Resource::query()->where('name', 'Deposit Court')->first()->deposit_per_slot)->toBe(20000);
});

test('a deposit at or above the price is rejected', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($operator)
        ->from(route('courts.index'))
        ->post(route('courts.store'), [
            'name' => 'Bad Court',
            'price' => 500,
            'deposit' => 500,
            'opens_at' => '06:00',
            'closes_at' => '22:00',
            'slot_minutes' => 60,
            'buffer_minutes' => 0,
            'booking_window_days' => 30,
        ])
        ->assertSessionHasErrors('deposit');
});
