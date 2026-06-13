<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\Concerns\NotifiesBookingPlayer;
use App\Services\WaitlistService;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleBookingCancellation implements ShouldQueue
{
    use NotifiesBookingPlayer;

    public function __construct(protected WaitlistService $waitlist) {}

    public function handle(BookingCancelled $event): void
    {
        self::sendFor($event->booking, new BookingCancelledNotification($event->booking));

        // Cancellation recovery: offer the freed slot to the waitlist.
        $this->waitlist->handleCancellation($event->booking);
    }
}
