<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    use Concerns;

    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    /**
     * Whether the status counts towards the attendance rate.
     */
    public function countsAsAttended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Excused => 'info',
        };
    }
}
