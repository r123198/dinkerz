<?php

namespace App\Notifications;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistSlotAvailable extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WaitlistEntry $entry) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resource = $this->entry->resource;
        $timezone = $resource->facility->timezone;
        $starts = $this->entry->starts_at->setTimezone($timezone);

        $bookingUrl = url(sprintf(
            '/%s/book?date=%s&resource=%d&waitlist=%s',
            $this->entry->tenant->slug,
            $starts->format('Y-m-d'),
            $resource->id,
            $this->entry->token,
        ));

        return (new MailMessage)
            ->subject('A court opened up — '.$resource->name)
            ->greeting('Good news!')
            ->line('A slot you wanted is now available:')
            ->line($resource->name.', '.$starts->toDayDateTimeString().' ('.$timezone.')')
            ->action('Book it now', $bookingUrl)
            ->line('First come, first served — this slot is open to everyone on the waitlist.');
    }
}
