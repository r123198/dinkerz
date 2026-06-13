<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Payments\PaymentManager;
use App\Services\WaitlistService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PortalController extends Controller
{
    /**
     * Days of availability shown on the public booking page.
     */
    private const VISIBLE_DAYS = 14;

    public function show(Request $request, Tenant $tenant, AvailabilityService $availability): Response
    {
        abort_unless($tenant->acceptsBookings(), 404);

        $timezone = $tenant->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $lastDay = $today->addDays(self::VISIBLE_DAYS - 1);

        $selected = rescue(
            fn () => CarbonImmutable::createFromFormat('Y-m-d', $request->string('date')->toString(), $timezone)->startOfDay(),
            $today,
            report: false,
        );
        $selected = $selected->betweenIncluded($today, $lastDay) ? $selected : $today;

        $courts = $tenant->resources()
            ->active()
            ->with('facility')
            ->orderBy('name')
            ->get()
            ->map(fn (Resource $court) => [
                'id' => $court->id,
                'name' => $court->name,
                'price' => $court->price_per_slot / 100,
                'deposit' => $court->depositAmount() / 100,
                'capacity' => $court->capacity,
                'slot_minutes' => $court->slot_minutes,
                'slots' => $availability->slotsFor($court, $selected->format('Y-m-d'))
                    ->map(fn (array $slot) => [
                        'starts_at' => $slot['starts_at']->utc()->toIso8601String(),
                        'label' => $slot['starts_at']->format('g:i A'),
                        'available' => $slot['available'],
                    ])
                    ->values(),
            ]);

        return Inertia::render('portal/Book', [
            'tenant' => $this->presentTenant($tenant),
            'courts' => $courts,
            'date' => $selected->format('Y-m-d'),
            'dates' => collect(range(0, self::VISIBLE_DAYS - 1))->map(fn (int $offset) => [
                'value' => $today->addDays($offset)->format('Y-m-d'),
                'weekday' => $today->addDays($offset)->format('D'),
                'day' => $today->addDays($offset)->format('j'),
            ]),
            'waitlistToken' => $request->string('waitlist')->toString() ?: null,
        ]);
    }

    public function store(
        Request $request,
        Tenant $tenant,
        BookingService $bookings,
        PaymentManager $payments,
        WaitlistService $waitlist,
    ): HttpResponse {
        abort_unless($tenant->acceptsBookings(), 404);

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:255'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'waitlist_token' => ['nullable', 'uuid'],
            // Multi-slot selection. Legacy single-slot fields are still accepted.
            'slots' => ['sometimes', 'array', 'min:1', 'max:12'],
            'slots.*.resource_id' => ['required_with:slots', 'integer'],
            'slots.*.starts_at' => ['required_with:slots', 'date'],
            'resource_id' => ['required_without:slots', 'integer'],
            'starts_at' => ['required_without:slots', 'date'],
        ]);

        $slots = $validated['slots'] ?? [[
            'resource_id' => $validated['resource_id'],
            'starts_at' => $validated['starts_at'],
        ]];

        // A group id only matters when several slots are booked together.
        $groupId = count($slots) > 1 ? (string) Str::uuid() : null;

        /** @var Collection<int, Booking> $held */
        $held = collect();
        $dropped = [];

        foreach ($slots as $slot) {
            $court = $tenant->resources()->active()->find($slot['resource_id']);

            if ($court === null) {
                continue;
            }

            $startsAt = CarbonImmutable::parse($slot['starts_at'])->utc();

            try {
                $held->push($bookings->createPendingBooking(
                    $court,
                    $startsAt,
                    guestName: $validated['guest_name'],
                    guestEmail: $validated['guest_email'],
                    partySize: $validated['party_size'] ?? null,
                    groupId: $groupId,
                ));
            } catch (SlotUnavailableException) {
                // Best-effort: skip the taken slot, keep the rest, and surface
                // it on the success screen so the player is never charged for
                // a slot they didn't get without being told.
                $dropped[] = $court->name.' · '
                    .$startsAt->setTimezone($tenant->timezone)->format('M j, g:i A');
            } catch (BookingException $exception) {
                return back()->withErrors(['guest_email' => $exception->getMessage()]);
            }
        }

        if ($held->isEmpty()) {
            return back()->withErrors([
                'slots' => 'Those slots were just taken. Please pick again.',
            ]);
        }

        if ($validated['waitlist_token'] ?? null) {
            $entry = $tenant->waitlistEntries()
                ->where('token', $validated['waitlist_token'])
                ->first();

            if ($entry !== null) {
                $waitlist->markConverted($entry);
            }
        }

        $payment = $payments->driver()->createCheckout(
            $held,
            successUrl: route('portal.booked', ['tenant' => $tenant->slug, 'reference' => $held->first()->reference]),
            cancelUrl: route('portal.home', ['tenant' => $tenant->slug]),
        );

        if ($dropped !== []) {
            $payment->update(['meta' => ['dropped' => $dropped]]);
        }

        return Inertia::location($payment->checkout_url);
    }

    public function joinWaitlist(Request $request, Tenant $tenant, WaitlistService $waitlist): HttpResponse
    {
        abort_unless($tenant->acceptsBookings(), 404);

        $validated = $request->validate([
            'resource_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:255'],
        ]);

        $court = $tenant->resources()->active()->findOrFail($validated['resource_id']);

        $waitlist->enroll(
            $court,
            CarbonImmutable::parse($validated['starts_at'])->utc(),
            guestName: $validated['guest_name'],
            guestEmail: $validated['guest_email'],
        );

        return back()->with('success', "You're on the waitlist — we'll email you if this slot opens up.");
    }

    public function confirmation(Tenant $tenant, string $reference): Response
    {
        $booking = $tenant->bookings()
            ->with(['resource.facility', 'payments' => fn ($query) => $query->latest()])
            ->where('reference', $reference)
            ->firstOrFail();

        $timezone = $booking->resource->facility->timezone;

        return Inertia::render('portal/Confirmation', [
            'tenant' => $this->presentTenant($tenant),
            'booking' => [
                'reference' => $booking->reference,
                'court' => $booking->resource->name,
                'starts_at' => $booking->starts_at->setTimezone($timezone)->format('l, F j · g:i A'),
                'status' => $booking->status->value,
                'amount' => $booking->amount / 100,
                'deposit_paid' => $booking->depositAmount() / 100,
                'balance_due' => $booking->balanceDue() / 100,
                'party_size' => $booking->party_size,
                'player_name' => $booking->playerName(),
                'checkout_url' => $booking->payments->first()?->checkout_url,
            ],
        ]);
    }

    /**
     * Self-service cancellation lookup — no login required. Players cancel
     * with the booking reference and the email they booked under.
     */
    public function showCancel(Request $request, Tenant $tenant): Response
    {
        abort_unless($tenant->acceptsBookings(), 404);

        return Inertia::render('portal/Cancel', [
            'tenant' => $this->presentTenant($tenant),
            'reference' => $request->string('reference')->toString() ?: null,
        ]);
    }

    public function cancel(Request $request, Tenant $tenant, BookingService $bookings): RedirectResponse
    {
        abort_unless($tenant->acceptsBookings(), 404);

        $validated = $request->validate([
            'reference' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $booking = $tenant->bookings()
            ->where('reference', $validated['reference'])
            ->first();

        // Combine the two checks so we never reveal whether a reference exists.
        $emailMatches = $booking
            && strcasecmp((string) $booking->playerEmail(), $validated['email']) === 0;

        if (! $booking || ! $emailMatches) {
            return back()->withErrors([
                'reference' => 'We couldn’t find a booking with that reference and email.',
            ]);
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            return back()->withErrors([
                'reference' => match ($booking->status) {
                    BookingStatus::Cancelled, BookingStatus::Refunded => 'This booking has already been cancelled.',
                    BookingStatus::PendingPayment => 'This booking isn’t confirmed yet — unpaid holds expire on their own.',
                    BookingStatus::Completed => 'This session has already taken place.',
                    default => 'This booking can no longer be cancelled.',
                },
            ]);
        }

        // Frees the slot and notifies the waitlist via the BookingCancelled event.
        $bookings->cancel($booking, metadata: ['source' => 'guest']);

        return redirect()
            ->route('portal.bookings.show', ['tenant' => $tenant->slug, 'reference' => $booking->reference])
            ->with('success', 'Your booking has been cancelled and the court released.');
    }

    /**
     * Terminal success screen shown after a payment attempt. It traps back
     * navigation so players can't return into the checkout for a slot they
     * just paid for. The booking reference is shown for future management.
     */
    public function success(Tenant $tenant, string $reference): Response
    {
        $booking = $tenant->bookings()
            ->where('reference', $reference)
            ->firstOrFail();

        // A group checkout confirms several slots together; show them all.
        $group = $booking->group_id
            ? $tenant->bookings()->with('resource.facility')
                ->where('group_id', $booking->group_id)->orderBy('starts_at')->get()
            : $tenant->bookings()->with('resource.facility')
                ->whereKey($booking->id)->get();

        $timezone = $tenant->timezone;

        $payment = Payment::query()
            ->whereIn('booking_id', $group->modelKeys())
            ->latest()
            ->first();

        return Inertia::render('portal/Success', [
            'tenant' => $this->presentTenant($tenant),
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'partySize' => $booking->party_size,
            'bookings' => $group->map(fn (Booking $b) => [
                'reference' => $b->reference,
                'court' => $b->resource->name,
                'starts_at' => $b->starts_at->setTimezone($b->resource->facility->timezone)->format('D, M j · g:i A'),
                'amount' => $b->amount / 100,
            ])->values(),
            'totals' => [
                'amount' => $group->sum('amount') / 100,
                'deposit_paid' => $group->sum(fn (Booking $b) => $b->depositAmount()) / 100,
                'balance_due' => $group->sum(fn (Booking $b) => $b->balanceDue()) / 100,
            ],
            'dropped' => data_get($payment, 'meta.dropped', []),
        ]);
    }

    /**
     * @return array{name: string, slug: string, color: string|null, logo: string|null}
     */
    private function presentTenant(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'color' => $tenant->primary_color,
            'logo' => $tenant->logo_path,
        ];
    }
}
