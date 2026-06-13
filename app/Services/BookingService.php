<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Exceptions\BookingException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Minutes a pending booking holds inventory while payment is in flight.
     */
    public const HOLD_MINUTES = 15;

    public function __construct(protected AvailabilityService $availability) {}

    /**
     * Place a temporary hold on a slot while payment is collected.
     *
     * @throws BookingException
     * @throws SlotUnavailableException
     */
    public function createPendingBooking(
        Resource $resource,
        CarbonImmutable $startsAt,
        ?User $user = null,
        ?string $guestName = null,
        ?string $guestEmail = null,
        ?int $partySize = null,
        ?string $groupId = null,
    ): Booking {
        $tenant = $resource->tenant;

        if (! $tenant->acceptsBookings()) {
            throw new BookingException('This facility is not currently accepting bookings.');
        }

        if ($tenant->subscription && ! $tenant->subscription->status->allowsBookings()) {
            throw new BookingException('This facility is not currently accepting bookings.');
        }

        if ($user === null && $guestEmail === null) {
            throw new BookingException('A player account or guest email is required.');
        }

        $endsAt = $startsAt->addMinutes($resource->slot_minutes);

        return DB::transaction(function () use ($resource, $startsAt, $endsAt, $user, $guestName, $guestEmail, $partySize, $groupId, $tenant) {
            // Serialize concurrent attempts against existing holds, then re-check
            // the grid. The pgsql exclusion constraint backstops insert races.
            $resource->bookings()
                ->blocking()
                ->overlapping($startsAt, $endsAt)
                ->lockForUpdate()
                ->get();

            if (! $this->availability->isSlotAvailable($resource, $startsAt)) {
                throw SlotUnavailableException::for();
            }

            try {
                $booking = $resource->bookings()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user?->id,
                    'group_id' => $groupId,
                    'guest_name' => $user ? null : $guestName,
                    'guest_email' => $user ? null : $guestEmail,
                    'party_size' => $partySize,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => BookingStatus::PendingPayment,
                    'amount' => $resource->price_per_slot,
                    'deposit_amount' => $resource->depositAmount(),
                    'currency' => $tenant->currency,
                    'expires_at' => now()->addMinutes(self::HOLD_MINUTES),
                ]);
            } catch (QueryException $exception) {
                throw SlotUnavailableException::for();
            }

            $booking->events()->create([
                'from_status' => null,
                'to_status' => BookingStatus::PendingPayment->value,
                'actor_id' => $user?->id,
            ]);

            return $booking;
        });
    }

    /**
     * Confirm a booking after verified payment. Only webhook-verified payment
     * flows should call this — never a frontend success redirect.
     */
    public function confirm(Booking $booking, array $metadata = []): Booking
    {
        $booking->transitionTo(BookingStatus::Confirmed, metadata: $metadata);

        event(new BookingConfirmed($booking));

        return $booking;
    }

    public function cancel(Booking $booking, ?User $actor = null, array $metadata = []): Booking
    {
        $booking->transitionTo(BookingStatus::Cancelled, $actor, $metadata);

        event(new BookingCancelled($booking));

        return $booking;
    }

    public function fail(Booking $booking, array $metadata = []): Booking
    {
        $booking->transitionTo(BookingStatus::Failed, metadata: $metadata);

        return $booking;
    }

    /**
     * Release inventory held by pending bookings whose hold has lapsed.
     *
     * @return int Number of bookings expired.
     */
    public function expireOverdue(): int
    {
        $expired = 0;

        Booking::query()
            ->where('status', BookingStatus::PendingPayment)
            ->where('expires_at', '<=', now())
            ->each(function (Booking $booking) use (&$expired) {
                $booking->transitionTo(BookingStatus::Expired, metadata: ['reason' => 'hold_lapsed']);
                $expired++;
            });

        return $expired;
    }

    /**
     * Mark confirmed bookings whose end time has passed as completed.
     *
     * @return int Number of bookings completed.
     */
    public function completeFinished(): int
    {
        $completed = 0;

        Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('ends_at', '<=', now())
            ->each(function (Booking $booking) use (&$completed) {
                $booking->transitionTo(BookingStatus::Completed);
                $completed++;
            });

        return $completed;
    }
}
