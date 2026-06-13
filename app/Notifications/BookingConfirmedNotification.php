<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $timezone = $this->booking->resource->facility->timezone;
        $starts = $this->booking->starts_at->setTimezone($timezone);

        return (new MailMessage)
            ->subject('Booking confirmed — '.$this->booking->resource->name)
            ->greeting('You\'re booked!')
            ->line($this->booking->resource->name.' at '.$this->booking->tenant->name)
            ->line($starts->toDayDateTimeString().' ('.$timezone.')')
            ->line('Reference: '.$this->booking->reference)
            ->line('See you on the court!');
    }
}
