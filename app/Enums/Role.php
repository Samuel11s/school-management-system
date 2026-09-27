<?php

namespace App\Enums;

enum Role: string
{
    use Concerns;

    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';

    /**
     * Permissions granted to each role. Ownership rules (a teacher's own
     * sections, a student's own records) are enforced by policies on top.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Teacher => [
                Permission::ViewStudents,
                Permission::ViewEnrollments,
                Permission::ManageGrades,
                Permission::ManageAttendance,
            ],
            self::Student => [],
        };
    }
}
