<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function __construct(private readonly SectionPolicy $sections) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->sections->manageAttendance($user, $record->section)
            || $record->student?->user_id === $user->id;
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $this->sections->manageAttendance($user, $record->section);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $this->update($user, $record);
    }
}
