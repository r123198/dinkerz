<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function allowsBookings(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }
}
