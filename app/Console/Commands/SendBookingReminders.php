<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\BookingReminderNotification;
use App\Notifications\Concerns\NotifiesBookingPlayer;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    use NotifiesBookingPlayer;

    /**
     * How far ahead of the start time reminders go out.
     */
    public const REMINDER_HOURS = 24;

    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send reminders for confirmed bookings starting soon';

    public function handle(): int
    {
        $sent = 0;

        Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addHours(self::REMINDER_HOURS)])
            ->with(['resource.facility', 'tenant', 'user'])
            ->each(function (Booking $booking) use (&$sent) {
                self::sendFor($booking, new BookingReminderNotification($booking));
                $booking->update(['reminder_sent_at' => now()]);
                $sent++;
            });

        $this->info("Sent {$sent} booking reminders.");

        return self::SUCCESS;
    }
}
