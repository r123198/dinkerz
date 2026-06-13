<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 08:00:00', 'Asia/Manila')->utc());

    $this->tenant = Tenant::factory()->create(['slug' => 'ace']);
    $facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->courtA = Resource::factory()->create([
        'tenant_id' => $this->tenant->id, 'facility_id' => $facility->id, 'name' => 'Court A', 'price_per_slot' => 50000,
    ]);
    $this->courtB = Resource::factory()->create([
        'tenant_id' => $this->tenant->id, 'facility_id' => $facility->id, 'name' => 'Court B', 'price_per_slot' => 50000,
    ]);
    $this->slot = CarbonImmutable::parse('2026-06-16 10:00', 'Asia/Manila');
});

function bookSlots(array $slots, array $overrides = [])
{
    return test()->post('/ace/book', [
        'guest_name' => 'Group Organizer',
        'guest_email' => 'organizer@example.com',
        'slots' => $slots,
        ...$overrides,
    ]);
}

test('booking multiple courts creates one payment for all of them', function () {
    bookSlots([
        ['resource_id' => $this->courtA->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
        ['resource_id' => $this->courtB->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
    ])->assertRedirect();

    $bookings = Booking::all();
    $payment = Payment::sole();

    expect($bookings)->toHaveCount(2)
        ->and($bookings->pluck('group_id')->unique())->toHaveCount(1)
        ->and($bookings->first()->group_id)->not->toBeNull()
        ->and($payment->amount)->toBe(100000)
        ->and($payment->relatedBookings())->toHaveCount(2);
});

test('paying once confirms every court in the group', function () {
    bookSlots([
        ['resource_id' => $this->courtA->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
        ['resource_id' => $this->courtB->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
    ]);

    $payment = Payment::sole();
    $this->post("/ace/payments/{$payment->id}/simulate", ['outcome' => 'paid'])->assertRedirect();

    expect(Booking::where('status', BookingStatus::Confirmed)->count())->toBe(2)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

test('best-effort: a taken slot is dropped and only the rest are charged', function () {
    // Court A @ 10:00 is already booked by someone else.
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->courtA->id,
        'starts_at' => $this->slot,
        'ends_at' => $this->slot->addHour(),
    ]);

    bookSlots([
        ['resource_id' => $this->courtA->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
        ['resource_id' => $this->courtB->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
    ])->assertRedirect();

    // Only Court B was held (plus the pre-existing Court A booking).
    $held = Booking::where('guest_email', 'organizer@example.com')->get();
    $payment = Payment::sole();

    expect($held)->toHaveCount(1)
        ->and($held->first()->resource_id)->toBe($this->courtB->id)
        ->and($payment->amount)->toBe(50000)
        ->and($payment->meta['dropped'])->toHaveCount(1)
        ->and($payment->meta['dropped'][0])->toContain('Court A');
});

test('when every selected slot is taken, nothing is booked', function () {
    foreach ([$this->courtA, $this->courtB] as $court) {
        Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'resource_id' => $court->id,
            'starts_at' => $this->slot,
            'ends_at' => $this->slot->addHour(),
        ]);
    }

    bookSlots([
        ['resource_id' => $this->courtA->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
        ['resource_id' => $this->courtB->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
    ])->assertSessionHasErrors('slots');

    expect(Booking::where('guest_email', 'organizer@example.com')->count())->toBe(0)
        ->and(Payment::count())->toBe(0);
});

test('the success page lists every court in the group', function () {
    bookSlots([
        ['resource_id' => $this->courtA->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
        ['resource_id' => $this->courtB->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
    ]);

    $reference = Booking::query()->whereNotNull('group_id')->orderBy('id')->value('reference');

    $this->get("/ace/booked/{$reference}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('portal/Success')
            ->has('bookings', 2)
            ->where('totals.amount', 1000));
});

test('a single-court booking still works through the same flow', function () {
    bookSlots([
        ['resource_id' => $this->courtA->id, 'starts_at' => $this->slot->utc()->toIso8601String()],
    ])->assertRedirect();

    $booking = Booking::sole();

    expect($booking->group_id)->toBeNull()
        ->and(Payment::sole()->amount)->toBe(50000);
});
