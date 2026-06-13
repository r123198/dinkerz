<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\Concerns\NotifiesBookingPlayer;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingConfirmation implements ShouldQueue
{
    use NotifiesBookingPlayer;

    public function handle(BookingConfirmed $event): void
    {
        self::sendFor($event->booking, new BookingConfirmedNotification($event->booking));
    }
}
