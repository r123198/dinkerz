<?php

use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\WaitlistSlotAvailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
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

function confirmedBooking(array $attributes = []): Booking
{
    return Booking::factory()->create([
        'tenant_id' => test()->tenant->id,
        'resource_id' => test()->court->id,
        'guest_name' => 'Ana Cruz',
        'guest_email' => 'ana@example.com',
        'starts_at' => test()->slot,
        'ends_at' => test()->slot->addHour(),
        'status' => BookingStatus::Confirmed,
        ...$attributes,
    ]);
}

test('the cancel page renders and prefills the reference', function () {
    $this->get('/ace/cancel?reference=abc-123')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('portal/Cancel')
            ->where('reference', 'abc-123'));
});

test('a guest cancels a confirmed booking with reference and email', function () {
    Notification::fake();

    $booking = confirmedBooking();

    $this->from('/ace/cancel')
        ->post('/ace/cancel', [
            'reference' => $booking->reference,
            'email' => 'ana@example.com',
        ])
        ->assertRedirect("/ace/bookings/{$booking->reference}");

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled);

    Notification::assertSentOnDemand(
        BookingCancelledNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ana@example.com',
    );
});

test('email match is case-insensitive', function () {
    $booking = confirmedBooking();

    $this->post('/ace/cancel', [
        'reference' => $booking->reference,
        'email' => 'ANA@example.com',
    ])->assertRedirect("/ace/bookings/{$booking->reference}");

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
});

test('cancelling notifies the waitlist for the freed slot', function () {
    Notification::fake();

    $booking = confirmedBooking();
    $entry = WaitlistEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot,
        'ends_at' => $this->slot->addHour(),
        'status' => WaitlistStatus::Waiting,
        'guest_email' => 'waiting@example.com',
    ]);

    $this->post('/ace/cancel', [
        'reference' => $booking->reference,
        'email' => 'ana@example.com',
    ]);

    expect($entry->refresh()->status)->toBe(WaitlistStatus::Notified);
    Notification::assertSentOnDemand(WaitlistSlotAvailable::class);
});

test('a wrong email does not cancel the booking', function () {
    $booking = confirmedBooking();

    $this->from('/ace/cancel')
        ->post('/ace/cancel', [
            'reference' => $booking->reference,
            'email' => 'someone-else@example.com',
        ])
        ->assertRedirect('/ace/cancel')
        ->assertSessionHasErrors('reference');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('an unknown reference reports a not-found error', function () {
    $this->from('/ace/cancel')
        ->post('/ace/cancel', [
            'reference' => 'does-not-exist',
            'email' => 'ana@example.com',
        ])
        ->assertSessionHasErrors('reference');
});

test('a booking from another tenant cannot be cancelled here', function () {
    $foreign = Booking::factory()->create([
        'guest_email' => 'ana@example.com',
        'status' => BookingStatus::Confirmed,
    ]);

    $this->from('/ace/cancel')
        ->post('/ace/cancel', [
            'reference' => $foreign->reference,
            'email' => 'ana@example.com',
        ])
        ->assertSessionHasErrors('reference');

    expect($foreign->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('an already-cancelled booking reports a friendly error', function () {
    $booking = confirmedBooking(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);

    $this->from('/ace/cancel')
        ->post('/ace/cancel', [
            'reference' => $booking->reference,
            'email' => 'ana@example.com',
        ])
        ->assertSessionHasErrors('reference');
});

test('a pending booking cannot be cancelled through the guest flow', function () {
    $booking = confirmedBooking([
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->from('/ace/cancel')
        ->post('/ace/cancel', [
            'reference' => $booking->reference,
            'email' => 'ana@example.com',
        ])
        ->assertSessionHasErrors('reference');

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});
