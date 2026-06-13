<?php

use App\Enums\BookingStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->facility = Facility::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->operator = User::factory()->operator($this->tenant)->create();
});

test('the dashboard reports revenue and utilization metrics', function () {
    $court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'price_per_slot' => 50000,
    ]);
    Booking::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $court->id,
        'amount' => 50000,
    ]);

    $this->actingAs($this->operator)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('operator/Dashboard')
            ->where('metrics.revenue.month', 50000)
            ->where('metrics.insights.total_bookings', 1)
            ->has('metrics.utilization.rate'));
});

test('operators can create a court priced in pesos', function () {
    $this->actingAs($this->operator)
        ->post(route('courts.store'), [
            'name' => 'Center Court',
            'price' => 650.50,
            'opens_at' => '07:00',
            'closes_at' => '21:00',
            'slot_minutes' => 60,
            'buffer_minutes' => 15,
            'booking_window_days' => 14,
        ])
        ->assertRedirect();

    $court = Resource::query()->where('name', 'Center Court')->first();

    expect($court)->not->toBeNull()
        ->and($court->tenant_id)->toBe($this->tenant->id)
        ->and($court->price_per_slot)->toBe(65050)
        ->and($court->buffer_minutes)->toBe(15);
});

test('operators can create a court that closes past midnight', function () {
    $this->actingAs($this->operator)
        ->post(route('courts.store'), [
            'name' => 'Night Court',
            'price' => 600,
            'opens_at' => '18:00',
            'closes_at' => '04:00',
            'slot_minutes' => 60,
            'buffer_minutes' => 0,
            'booking_window_days' => 30,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $court = Resource::query()->where('name', 'Night Court')->first();

    expect($court)->not->toBeNull()
        ->and($court->operatingMinutesPerDay())->toBe(600);
});

test('the subscription court limit is enforced', function () {
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan' => SubscriptionPlan::Starter,
    ]);
    Resource::factory(4)->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
    ]);

    $this->actingAs($this->operator)
        ->from(route('courts.index'))
        ->post(route('courts.store'), [
            'name' => 'Court 5',
            'price' => 500,
            'opens_at' => '06:00',
            'closes_at' => '22:00',
            'slot_minutes' => 60,
            'buffer_minutes' => 0,
            'booking_window_days' => 30,
        ])
        ->assertSessionHasErrors('name');

    expect($this->tenant->resources()->count())->toBe(4);
});

test('operators cannot touch another tenants court', function () {
    $foreignCourt = Resource::factory()->create();

    $this->actingAs($this->operator)
        ->put(route('courts.update', $foreignCourt), [
            'name' => 'Hijacked',
            'price' => 1,
            'opens_at' => '06:00',
            'closes_at' => '22:00',
            'slot_minutes' => 60,
            'buffer_minutes' => 0,
            'booking_window_days' => 30,
        ])
        ->assertNotFound();
});

test('the bookings list is scoped to the tenant', function () {
    $court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
    ]);
    Booking::factory()->create(['tenant_id' => $this->tenant->id, 'resource_id' => $court->id]);
    Booking::factory()->create(); // belongs to a different tenant

    $this->actingAs($this->operator)
        ->get(route('bookings.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('operator/Bookings')
            ->has('bookings.data', 1));
});

test('operators can cancel a confirmed booking', function () {
    $court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
    ]);
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $court->id,
    ]);

    $this->actingAs($this->operator)
        ->post(route('bookings.cancel', $booking))
        ->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
});

test('archiving hides a court from the active list', function () {
    $court = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
    ]);

    $this->actingAs($this->operator)
        ->delete(route('courts.destroy', $court))
        ->assertRedirect();

    expect($court->refresh()->isArchived())->toBeTrue();

    $this->actingAs($this->operator)
        ->get(route('courts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('courts', 0));
});
