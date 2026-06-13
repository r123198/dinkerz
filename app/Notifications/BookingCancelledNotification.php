<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject('Booking cancelled — '.$this->booking->resource->name)
            ->line('Your booking at '.$this->booking->tenant->name.' has been cancelled.')
            ->line($this->booking->resource->name.', '.$this->booking->starts_at->setTimezone($timezone)->toDayDateTimeString())
            ->line('Reference: '.$this->booking->reference);
    }
}
