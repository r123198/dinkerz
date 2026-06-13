<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create(['slug' => 'ace']);
    $facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $facility->id,
    ]);
    $this->slot = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');
});

test('the booking page shows courts and slots for a date', function () {
    $this->get('/ace?date=2026-06-16')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('portal/Book')
            ->where('tenant.name', $this->tenant->name)
            ->where('date', '2026-06-16')
            ->has('courts', 1)
            ->has('courts.0.slots', 16)
            ->where('courts.0.slots.0.available', true));
});

test('suspended tenants have no public portal', function () {
    $this->tenant->update(['status' => TenantStatus::Suspended]);

    $this->get('/ace')->assertNotFound();
});

test('guest checkout creates a held booking and redirects to payment', function () {
    $response = $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ana Cruz',
        'guest_email' => 'ana@example.com',
    ]);

    $booking = Booking::sole();
    $payment = Payment::sole();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->guest_email)->toBe('ana@example.com')
        ->and($payment->booking_id)->toBe($booking->id)
        ->and($payment->checkout_url)->toContain('/simulate');

    // External redirect to the provider checkout.
    $response->assertRedirect($payment->checkout_url);
});

test('booking a taken slot reports the conflict', function () {
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot,
        'ends_at' => $this->slot->addHour(),
    ]);

    $this->from('/ace')->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ben',
        'guest_email' => 'ben@example.com',
    ])->assertSessionHasErrors('slots');

    expect(Booking::count())->toBe(1);
});

test('players can join the waitlist from the portal', function () {
    $this->from('/ace')->post('/ace/waitlist', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ben',
        'guest_email' => 'ben@example.com',
    ])->assertRedirect('/ace');

    expect(WaitlistEntry::sole()->status)->toBe(WaitlistStatus::Waiting);
});

test('completing the simulated payment confirms the booking', function () {
    $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ana Cruz',
        'guest_email' => 'ana@example.com',
    ]);

    $payment = Payment::sole();

    $this->post("/ace/payments/{$payment->id}/simulate", ['outcome' => 'paid'])
        ->assertRedirect("/ace/booked/{$payment->booking->reference}");

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->booking->status)->toBe(BookingStatus::Confirmed);
});

test('the checkout page renders while payment is pending', function () {
    $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ana Cruz',
        'guest_email' => 'ana@example.com',
    ]);

    $payment = Payment::sole();

    $this->get("/ace/payments/{$payment->id}/simulate")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('portal/PaySimulator'));
});

test('the spent checkout cannot be reopened after payment', function () {
    $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ana Cruz',
        'guest_email' => 'ana@example.com',
    ]);

    $payment = Payment::sole();
    $this->post("/ace/payments/{$payment->id}/simulate", ['outcome' => 'paid']);

    // Trying to revisit the checkout bounces forward to the success page.
    $this->get("/ace/payments/{$payment->id}/simulate")
        ->assertRedirect("/ace/booked/{$payment->refresh()->booking->reference}");
});

test('the success page shows the booking reference', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->court->id,
    ]);

    $this->get("/ace/booked/{$booking->reference}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('portal/Success')
            ->where('reference', $booking->reference)
            ->where('status', 'confirmed')
            ->has('bookings', 1)
            ->where('bookings.0.reference', $booking->reference));
});

test('booking through a waitlist token converts the entry', function () {
    $entry = WaitlistEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot,
        'ends_at' => $this->slot->addHour(),
        'status' => WaitlistStatus::Notified,
    ]);

    $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ben',
        'guest_email' => 'ben@example.com',
        'waitlist_token' => $entry->token,
    ]);

    expect($entry->refresh()->status)->toBe(WaitlistStatus::Converted);
});

test('the confirmation page shows booking status', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->court->id,
    ]);

    $this->get("/ace/bookings/{$booking->reference}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('portal/Confirmation')
            ->where('booking.status', 'confirmed')
            ->where('booking.reference', $booking->reference));
});

test('a tenant cannot view another tenants booking confirmation', function () {
    $foreign = Booking::factory()->create();

    $this->get("/ace/bookings/{$foreign->reference}")->assertNotFound();
});
