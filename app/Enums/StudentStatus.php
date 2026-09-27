<?php

namespace App\Enums;

enum StudentStatus: string
{
    use Concerns;

    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';

    public function canEnroll(): bool
    {
        return $this === self::Active;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Suspended => 'danger',
            self::Graduated => 'primary',
            self::Withdrawn => 'warning',
        };
    }
}
