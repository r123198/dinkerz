<?php

namespace App\Services;

use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistSlotAvailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

class WaitlistService
{
    /**
     * Join the waitlist for a taken slot. Re-joining the same slot with the
     * same email returns the existing entry instead of duplicating it.
     */
    public function enroll(
        Resource $resource,
        CarbonImmutable $startsAt,
        ?User $user = null,
        ?string $guestName = null,
        ?string $guestEmail = null,
    ): WaitlistEntry {
        $email = $user?->email ?? $guestEmail;

        throw_if($email === null, new \InvalidArgumentException('A player account or guest email is required.'));

        $endsAt = $startsAt->addMinutes($resource->slot_minutes);

        $existing = $resource->waitlistEntries()
            ->where('status', WaitlistStatus::Waiting)
            ->where('starts_at', $startsAt->utc())
            ->where(fn ($query) => $user
                ? $query->where('user_id', $user->id)
                : $query->where('guest_email', $email))
            ->first();

        return $existing ?? $resource->waitlistEntries()->create([
            'tenant_id' => $resource->tenant_id,
            'user_id' => $user?->id,
            'guest_name' => $user ? null : $guestName,
            'guest_email' => $email,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => WaitlistStatus::Waiting,
        ]);
    }

    /**
     * A cancellation freed inventory: notify every waiting player whose
     * desired slot overlaps it. First to complete checkout wins the slot.
     */
    public function handleCancellation(Booking $booking): int
    {
        $notified = 0;

        $booking->resource->waitlistEntries()
            ->where('status', WaitlistStatus::Waiting)
            ->where('starts_at', '<', $booking->ends_at)
            ->where('ends_at', '>', $booking->starts_at)
            ->each(function (WaitlistEntry $entry) use (&$notified) {
                $entry->update([
                    'status' => WaitlistStatus::Notified,
                    'notified_at' => now(),
                ]);

                $notifiable = $entry->user
                    ?? Notification::route('mail', $entry->guest_email);
                $notifiable->notify(new WaitlistSlotAvailable($entry));

                $notified++;
            });

        return $notified;
    }

    /**
     * The waitlisted player completed a booking for their slot.
     */
    public function markConverted(WaitlistEntry $entry): void
    {
        $entry->update(['status' => WaitlistStatus::Converted]);
    }
}
