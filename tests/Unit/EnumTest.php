<?php

namespace Tests\Unit;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StudentStatus;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_admin_has_every_permission(): void
    {
        $this->assertSame(Permission::cases(), Role::Admin->permissions());
    }

    public function test_teacher_cannot_manage_records_outside_teaching(): void
    {
        $permissions = Role::Teacher->permissions();

        $this->assertContains(Permission::ManageGrades, $permissions);
        $this->assertContains(Permission::ManageAttendance, $permissions);
        $this->assertNotContains(Permission::ManageStudents, $permissions);
        $this->assertNotContains(Permission::ManageEnrollments, $permissions);
        $this->assertNotContains(Permission::ManageUsers, $permissions);
    }

    public function test_students_have_no_global_permissions(): void
    {
        $this->assertSame([], Role::Student->permissions());
    }

    public function test_only_active_students_can_enroll(): void
    {
        foreach (StudentStatus::cases() as $status) {
            $this->assertSame($status === StudentStatus::Active, $status->canEnroll());
        }
    }

    public function test_attended_statuses(): void
    {
        $this->assertTrue(AttendanceStatus::Present->countsAsAttended());
        $this->assertTrue(AttendanceStatus::Late->countsAsAttended());
        $this->assertFalse(AttendanceStatus::Absent->countsAsAttended());
        $this->assertFalse(AttendanceStatus::Excused->countsAsAttended());
    }

    public function test_only_enrolled_status_holds_a_seat(): void
    {
        $this->assertSame(['enrolled'], EnrollmentStatus::seatHolding());
    }

    public function test_options_are_value_label_pairs(): void
    {
        $this->assertSame(['admin' => 'Admin', 'teacher' => 'Teacher', 'student' => 'Student'], Role::options());
    }
}
