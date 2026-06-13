<?php

namespace App\Notifications\Concerns;

use App\Models\Booking;
use Illuminate\Support\Facades\Notification;

trait NotifiesBookingPlayer
{
    /**
     * Notify the booking's player, whether registered or guest.
     */
    public static function sendFor(Booking $booking, object $notification): void
    {
        if ($booking->user) {
            $booking->user->notify($notification);

            return;
        }

        if ($booking->guest_email) {
            Notification::route('mail', $booking->guest_email)->notify($notification);
        }
    }
}
