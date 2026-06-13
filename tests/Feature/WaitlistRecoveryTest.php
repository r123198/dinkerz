<?php

use App\Enums\WaitlistStatus;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\WaitlistSlotAvailable;
use App\Services\BookingService;
use App\Services\WaitlistService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create();
    $facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $facility->id,
    ]);
    $this->slot = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');
    $this->waitlist = app(WaitlistService::class);
});

test('players can join the waitlist for a taken slot', function () {
    $entry = $this->waitlist->enroll($this->court, $this->slot, guestName: 'Ben', guestEmail: 'ben@example.com');

    expect($entry->status)->toBe(WaitlistStatus::Waiting)
        ->and($entry->token)->not->toBeNull()
        ->and($entry->ends_at->getTimestamp())->toBe($this->slot->addHour()->getTimestamp());
});

test('joining the same slot twice does not duplicate the entry', function () {
    $first = $this->waitlist->enroll($this->court, $this->slot, guestEmail: 'ben@example.com');
    $second = $this->waitlist->enroll($this->court, $this->slot, guestEmail: 'ben@example.com');

    expect($second->id)->toBe($first->id)
        ->and(WaitlistEntry::count())->toBe(1);
});

test('cancellation notifies everyone waiting on the overlapping slot', function () {
    Notification::fake();

    $bookings = app(BookingService::class);
    $booking = $bookings->createPendingBooking($this->court, $this->slot, guestEmail: 'holder@example.com');
    $bookings->confirm($booking);

    $guest = $this->waitlist->enroll($this->court, $this->slot, guestEmail: 'guest@example.com');
    $player = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $playerEntry = $this->waitlist->enroll($this->court, $this->slot, user: $player);
    $otherSlot = $this->waitlist->enroll($this->court, $this->slot->addHours(3), guestEmail: 'later@example.com');

    $bookings->cancel($booking);

    expect($guest->refresh()->status)->toBe(WaitlistStatus::Notified)
        ->and($guest->notified_at)->not->toBeNull()
        ->and($playerEntry->refresh()->status)->toBe(WaitlistStatus::Notified)
        ->and($otherSlot->refresh()->status)->toBe(WaitlistStatus::Waiting);

    Notification::assertSentOnDemand(
        WaitlistSlotAvailable::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'guest@example.com'
    );
    Notification::assertSentTo($player, WaitlistSlotAvailable::class);
    Notification::assertSentOnDemand(
        BookingCancelledNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'holder@example.com'
    );
});

test('converted entries stop receiving offers', function () {
    $entry = $this->waitlist->enroll($this->court, $this->slot, guestEmail: 'ben@example.com');

    $this->waitlist->markConverted($entry);

    expect($entry->refresh()->status)->toBe(WaitlistStatus::Converted);
});
