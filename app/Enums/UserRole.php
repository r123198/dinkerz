<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Operator = 'operator';
    case Staff = 'staff';
    case Player = 'player';

    public function isOperator(): bool
    {
        return $this === self::Operator;
    }

    public function canManageFacility(): bool
    {
        return in_array($this, [self::Operator, self::Staff], true);
    }
}
