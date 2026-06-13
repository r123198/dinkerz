<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Expired = 'expired';
    case Failed = 'failed';

    /**
     * Statuses that occupy court inventory.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::PendingPayment, self::Confirmed, self::Completed];
    }

    /**
     * Valid state machine transitions.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingPayment, self::Expired],
            self::PendingPayment => [self::Confirmed, self::Expired, self::Failed],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Cancelled => [self::Refunded],
            self::Completed, self::Refunded, self::Expired, self::Failed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return in_array($this, self::blocking(), true);
    }
}
