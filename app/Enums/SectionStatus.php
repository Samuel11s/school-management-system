<?php

namespace App\Enums;

enum SectionStatus: string
{
    use Concerns;

    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'secondary',
            self::Cancelled => 'danger',
        };
    }
}
