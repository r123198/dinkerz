<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Keep test-now in UTC: a zoned test-now leaks its timezone into
    // Carbon instances parsed from the database.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create();
    $this->facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->availability = app(AvailabilityService::class);
});

function makeCourt(array $attributes = []): Resource
{
    return Resource::factory()->create([
        'tenant_id' => test()->tenant->id,
        'facility_id' => test()->facility->id,
        ...$attributes,
    ]);
}

test('generates slots from operating hours and slot duration', function () {
    $court = makeCourt(['opens_at' => '06:00', 'closes_at' => '22:00', 'slot_minutes' => 60]);

    $slots = $this->availability->slotsFor($court, '2026-06-16');

    expect($slots)->toHaveCount(16)
        ->and($slots->first()['starts_at']->format('H:i'))->toBe('06:00')
        ->and($slots->last()['starts_at']->format('H:i'))->toBe('21:00')
        ->and($slots->last()['ends_at']->format('H:i'))->toBe('22:00')
        ->and($slots->every(fn ($slot) => $slot['available']))->toBeTrue();
});

test('buffer time pushes subsequent slots later', function () {
    $court = makeCourt(['slot_minutes' => 60, 'buffer_minutes' => 15]);

    $slots = $this->availability->slotsFor($court, '2026-06-16');

    expect($slots)->toHaveCount(13)
        ->and($slots[0]['starts_at']->format('H:i'))->toBe('06:00')
        ->and($slots[1]['starts_at']->format('H:i'))->toBe('07:15')
        ->and($slots[2]['starts_at']->format('H:i'))->toBe('08:30');
});

test('slots overlapping a blocking booking are unavailable', function () {
    $court = makeCourt();
    $start = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');

    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $court->id,
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
    ]);

    $slots = $this->availability->slotsFor($court, '2026-06-16');
    $booked = $slots->first(fn ($slot) => $slot['starts_at']->equalTo($start));

    expect($booked['available'])->toBeFalse()
        ->and($slots->filter(fn ($slot) => $slot['available']))->toHaveCount(15);
});

test('cancelled bookings do not block slots', function () {
    $court = makeCourt();
    $start = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');

    Booking::factory()->cancelled()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $court->id,
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
    ]);

    expect($this->availability->isSlotAvailable($court, $start))->toBeTrue();
});

test('operator blocks hide inventory', function () {
    $court = makeCourt();

    ResourceBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $court->id,
        'starts_at' => CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila'),
        'ends_at' => CarbonImmutable::parse('2026-06-16 12:00', 'Asia/Manila'),
    ]);

    $available = $this->availability->slotsFor($court, '2026-06-16')
        ->filter(fn ($slot) => $slot['available']);

    expect($available)->toHaveCount(14)
        ->and($available->pluck('starts_at')->map->format('H:i'))
        ->not->toContain('10:00', '11:00');
});

test('past slots on the current day are unavailable', function () {
    $court = makeCourt();

    // Test now is 08:00 in Manila; earlier slots and the one starting
    // exactly now are no longer sellable.
    $slots = $this->availability->slotsFor($court, '2026-06-15');
    $past = $slots->filter(fn ($slot) => ! $slot['available']);

    expect($past->pluck('starts_at')->map->format('H:i')->values()->all())
        ->toBe(['06:00', '07:00', '08:00']);
});

test('dates outside the booking window have no slots', function () {
    $court = makeCourt(['booking_window_days' => 30]);

    expect($this->availability->slotsFor($court, '2026-07-16'))->toBeEmpty()
        ->and($this->availability->slotsFor($court, '2026-06-14'))->toBeEmpty()
        ->and($this->availability->slotsFor($court, '2026-07-15'))->not->toBeEmpty();
});

test('archived courts have no slots', function () {
    $court = makeCourt(['archived_at' => now()]);

    expect($this->availability->slotsFor($court, '2026-06-16'))->toBeEmpty();
});

test('courts that close past midnight generate slots into the next day', function () {
    $court = makeCourt(['opens_at' => '18:00', 'closes_at' => '04:00', 'slot_minutes' => 60]);

    $slots = $this->availability->slotsFor($court, '2026-06-16');

    expect($slots)->toHaveCount(10)
        ->and($slots->first()['starts_at']->format('Y-m-d H:i'))->toBe('2026-06-16 18:00')
        ->and($slots->last()['starts_at']->format('Y-m-d H:i'))->toBe('2026-06-17 03:00')
        ->and($slots->last()['ends_at']->format('Y-m-d H:i'))->toBe('2026-06-17 04:00')
        ->and($slots->every(fn ($slot) => $slot['available']))->toBeTrue();
});

test('a past-midnight slot is bookable against its opening session', function () {
    $court = makeCourt(['opens_at' => '18:00', 'closes_at' => '04:00', 'slot_minutes' => 60]);

    // 2 AM on the 17th belongs to the session that opened the evening of the 16th.
    $earlyMorning = CarbonImmutable::parse('2026-06-17 02:00', 'Asia/Manila');

    expect($this->availability->isSlotAvailable($court, $earlyMorning))->toBeTrue();
});

test('a same-day court is not bookable in the early morning', function () {
    $court = makeCourt(['opens_at' => '06:00', 'closes_at' => '22:00', 'slot_minutes' => 60]);

    $earlyMorning = CarbonImmutable::parse('2026-06-17 02:00', 'Asia/Manila');

    expect($this->availability->isSlotAvailable($court, $earlyMorning))->toBeFalse();
});
