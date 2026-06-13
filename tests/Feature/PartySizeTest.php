<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create(['slug' => 'ace']);
    $facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $facility->id,
        'capacity' => 4,
    ]);
    $this->slot = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');
});

test('suggested courts rounds a party up against court capacity', function () {
    expect($this->court->suggestedCourtsFor(14))->toBe(4)
        ->and($this->court->suggestedCourtsFor(4))->toBe(1)
        ->and($this->court->suggestedCourtsFor(5))->toBe(2)
        ->and($this->court->suggestedCourtsFor(1))->toBe(1);
});

test('party size is optional — booking works without it', function () {
    $booking = app(BookingService::class)
        ->createPendingBooking($this->court, $this->slot, guestEmail: 'ana@example.com');

    expect($booking->party_size)->toBeNull();
});

test('party size is recorded when provided', function () {
    $booking = app(BookingService::class)
        ->createPendingBooking($this->court, $this->slot, guestEmail: 'ana@example.com', partySize: 6);

    expect($booking->party_size)->toBe(6);
});

test('the portal stores party size from the booking form', function () {
    $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ana Cruz',
        'guest_email' => 'ana@example.com',
        'party_size' => 8,
    ]);

    expect(Booking::sole()->party_size)->toBe(8);
});

test('a large party still books a single court without friction', function () {
    // 14 players would be suggested ~4 courts, but the booking is never blocked
    // and only ever reserves the one slot the player chose.
    $this->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Big Group',
        'guest_email' => 'group@example.com',
        'party_size' => 14,
    ]);

    expect(Booking::count())->toBe(1)
        ->and(Booking::sole()->party_size)->toBe(14);
});

test('an invalid party size is rejected', function () {
    $this->from('/ace')->post('/ace/book', [
        'resource_id' => $this->court->id,
        'starts_at' => $this->slot->utc()->toIso8601String(),
        'guest_name' => 'Ana',
        'guest_email' => 'ana@example.com',
        'party_size' => 0,
    ])->assertSessionHasErrors('party_size');

    expect(Booking::count())->toBe(0);
});

test('the booking page exposes court capacity for the suggestion', function () {
    $this->get('/ace?date=2026-06-16')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('courts.0.capacity', 4));
});
