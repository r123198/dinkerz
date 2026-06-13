<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Tenant;
use App\Notifications\BookingReminderNotification;
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
});

function makeConfirmedBooking(CarbonImmutable $startsAt): Booking
{
    return Booking::factory()->create([
        'tenant_id' => test()->tenant->id,
        'resource_id' => test()->court->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
    ]);
}

test('reminders go to bookings starting within the next 24 hours', function () {
    Notification::fake();

    $soon = makeConfirmedBooking(now()->addHours(5)->toImmutable());
    $far = makeConfirmedBooking(now()->addHours(48)->toImmutable());

    $this->artisan('bookings:send-reminders')->assertSuccessful();

    expect($soon->refresh()->reminder_sent_at)->not->toBeNull()
        ->and($far->refresh()->reminder_sent_at)->toBeNull();

    Notification::assertSentOnDemand(
        BookingReminderNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $soon->guest_email
    );
});

test('reminders are not sent twice', function () {
    Notification::fake();

    makeConfirmedBooking(now()->addHours(5)->toImmutable());

    $this->artisan('bookings:send-reminders')->assertSuccessful();
    $this->artisan('bookings:send-reminders')->assertSuccessful();

    Notification::assertCount(1);
});

test('the lifecycle command expires holds and completes finished bookings', function () {
    $pending = Booking::factory()->pendingPayment()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->court->id,
        'expires_at' => now()->subMinute(),
    ]);
    $finished = makeConfirmedBooking(now()->subHours(2)->toImmutable());

    $this->artisan('bookings:process-lifecycle')->assertSuccessful();

    expect($pending->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($finished->refresh()->status)->toBe(BookingStatus::Completed);
});
