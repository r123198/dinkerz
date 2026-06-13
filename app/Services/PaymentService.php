<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Payments\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(protected BookingService $bookings) {}

    /**
     * Apply a verified webhook event exactly once. Duplicate provider events
     * are absorbed by the unique (provider, provider_event_id) index.
     */
    public function process(WebhookEvent $event): void
    {
        DB::transaction(function () use ($event) {
            $payment = Payment::query()
                ->where('provider', $event->provider)
                ->where('provider_id', $event->providerPaymentId)
                ->lockForUpdate()
                ->first();

            try {
                PaymentEvent::create([
                    'payment_id' => $payment?->id,
                    'provider' => $event->provider,
                    'provider_event_id' => $event->eventId,
                    'type' => $event->type,
                    'payload' => $event->payload,
                ]);
            } catch (UniqueConstraintViolationException) {
                return; // Already processed — webhooks must be idempotent.
            }

            if ($payment === null) {
                Log::warning('Webhook event received for unknown payment.', [
                    'provider' => $event->provider,
                    'event_id' => $event->eventId,
                    'provider_payment_id' => $event->providerPaymentId,
                ]);

                return;
            }

            // One payment may cover several slots booked together.
            $relatedBookings = $payment->relatedBookings();

            if ($event->isPaid() && $payment->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

                foreach ($relatedBookings as $booking) {
                    try {
                        $this->bookings->confirm($booking, ['payment_id' => $payment->id]);
                    } catch (\DomainException) {
                        // The hold lapsed before the webhook landed; flag for
                        // operator follow-up instead of double-selling the slot.
                        Log::warning('Payment received for a non-confirmable booking.', [
                            'payment_id' => $payment->id,
                            'booking_id' => $booking->id,
                            'booking_status' => $booking->status->value,
                        ]);
                    }
                }
            }

            if ($event->isFailed() && $payment->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Failed]);

                foreach ($relatedBookings as $booking) {
                    if ($booking->status->isActive()) {
                        $this->bookings->fail($booking, ['payment_id' => $payment->id]);
                    }
                }
            }
        });
    }
}
