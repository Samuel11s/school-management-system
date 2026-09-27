<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    use Concerns;

    case Enrolled = 'enrolled';
    case Dropped = 'dropped';
    case Completed = 'completed';

    /**
     * Statuses that occupy a seat in a section.
     *
     * @return list<string>
     */
    public static function seatHolding(): array
    {
        return [self::Enrolled->value];
    }

    public function badge(): string
    {
        return match ($this) {
            self::Enrolled => 'success',
            self::Dropped => 'secondary',
            self::Completed => 'primary',
        };
    }
}
