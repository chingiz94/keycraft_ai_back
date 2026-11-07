<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Assembled = 'assembled';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::New => in_array($next, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($next, [self::Assembled, self::Cancelled], true),
            self::Assembled => $next === self::Issued,
            self::Issued, self::Cancelled => false,
        };
    }
}
