<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Starter = 'starter';
    case Growth = 'growth';
    case Enterprise = 'enterprise';

    public function courtLimit(): ?int
    {
        return match ($this) {
            self::Starter => 4,
            self::Growth => 20,
            self::Enterprise => null,
        };
    }
}
