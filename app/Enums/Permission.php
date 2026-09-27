<?php

namespace App\Enums;

enum Permission: string
{
    use Concerns;

    case ViewStudents = 'students.view';
    case ManageStudents = 'students.manage';
    case ManageCourses = 'courses.manage';
    case ManageTerms = 'terms.manage';
    case ManageSections = 'sections.manage';
    case ViewEnrollments = 'enrollments.view';
    case ManageEnrollments = 'enrollments.manage';
    case ManageGrades = 'grades.manage';
    case ManageAttendance = 'attendance.manage';
    case ManageUsers = 'users.manage';
}
