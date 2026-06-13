<?php

namespace App\Exceptions;

class SlotUnavailableException extends BookingException
{
    public static function for(string $reason = 'That time slot is no longer available.'): self
    {
        return new self($reason);
    }
}
