<?php

use App\Enums\BookingStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Exceptions\BookingException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    // Keep test-now in UTC: a zoned test-now leaks its timezone into
    // Carbon instances parsed from the database.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create();
    $facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $facility->id,
    ]);
    $this->slot = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');
    $this->bookings = app(BookingService::class);
});

test('creates a pending booking holding the slot', function () {
    $booking = $this->bookings->createPendingBooking(
        $this->court, $this->slot, guestName: 'Ana Cruz', guestEmail: 'ana@example.com'
    );

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->reference)->not->toBeNull()
        ->and($booking->amount)->toBe($this->court->price_per_slot)
        ->and($booking->expires_at->getTimestamp())
        ->toBe(now()->addMinutes(BookingService::HOLD_MINUTES)->getTimestamp())
        ->and($booking->events()->count())->toBe(1);
});

test('a held slot cannot be double booked', function () {
    $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'first@example.com');

    $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'second@example.com');
})->throws(SlotUnavailableException::class);

test('expired holds free the slot for rebooking', function () {
    $booking = $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'a@example.com');

    CarbonImmutable::setTestNow(now()->addMinutes(BookingService::HOLD_MINUTES + 1));

    expect($this->bookings->expireOverdue())->toBe(1)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Expired);

    $rebooked = $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'b@example.com');

    expect($rebooked->status)->toBe(BookingStatus::PendingPayment);
});

test('confirming a booking fires BookingConfirmed', function () {
    Event::fake([BookingConfirmed::class]);

    $booking = $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'a@example.com');
    $this->bookings->confirm($booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
    Event::assertDispatched(BookingConfirmed::class);
});

test('cancelling returns inventory and fires BookingCancelled', function () {
    Event::fake([BookingCancelled::class]);

    $booking = $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'a@example.com');
    $this->bookings->confirm($booking);
    $this->bookings->cancel($booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_at)->not->toBeNull();

    Event::assertDispatched(BookingCancelled::class);

    $rebooked = $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'b@example.com');
    expect($rebooked->status)->toBe(BookingStatus::PendingPayment);
});

test('invalid state transitions are rejected', function () {
    $booking = $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'a@example.com');

    $this->bookings->cancel($booking); // pending_payment → cancelled is not allowed
})->throws(DomainException::class);

test('suspended tenants cannot accept bookings', function () {
    $this->tenant->update(['status' => TenantStatus::Suspended]);
    $this->court->refresh();

    $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'a@example.com');
})->throws(BookingException::class);

test('tenants with a lapsed subscription cannot accept bookings', function () {
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriptionStatus::Suspended,
    ]);
    $this->court->refresh();

    $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'a@example.com');
})->throws(BookingException::class);

test('slots outside the booking window cannot be booked', function () {
    $outside = CarbonImmutable::parse('2026-08-01 10:00', 'Asia/Manila');

    $this->bookings->createPendingBooking($this->court, $outside, guestEmail: 'a@example.com');
})->throws(SlotUnavailableException::class);

test('off-grid times cannot be booked', function () {
    $offGrid = CarbonImmutable::parse('2026-06-16 10:30', 'Asia/Manila');

    $this->bookings->createPendingBooking($this->court, $offGrid, guestEmail: 'a@example.com');
})->throws(SlotUnavailableException::class);

test('guest bookings require an email', function () {
    $this->bookings->createPendingBooking($this->court, $this->slot);
})->throws(BookingException::class);

test('registered players book under their account', function () {
    $player = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $booking = $this->bookings->createPendingBooking($this->court, $this->slot, user: $player);

    expect($booking->user_id)->toBe($player->id)
        ->and($booking->guest_email)->toBeNull()
        ->and($booking->playerEmail())->toBe($player->email);
});

test('past-midnight slots on overnight courts can be booked', function () {
    $this->court->update(['opens_at' => '18:00', 'closes_at' => '04:00']);
    $this->court->refresh();

    $lateSlot = CarbonImmutable::parse('2026-06-17 01:00', 'Asia/Manila');

    $booking = $this->bookings->createPendingBooking($this->court, $lateSlot, guestEmail: 'night@example.com');

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->starts_at->equalTo($lateSlot))->toBeTrue()
        ->and($booking->ends_at->equalTo($lateSlot->addHour()))->toBeTrue();
});

test('finished confirmed bookings are marked completed', function () {
    $booking = $this->bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'a@example.com');
    $this->bookings->confirm($booking);

    CarbonImmutable::setTestNow($this->slot->addHours(2)->utc());

    expect($this->bookings->completeFinished())->toBe(1)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Completed);
});
