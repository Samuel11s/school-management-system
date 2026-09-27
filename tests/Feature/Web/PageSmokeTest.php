<?php

namespace Tests\Feature\Web;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Renders every page with the demo data set for each role, guarding against
 * view errors and verifying the navigation each role is allowed to use.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(DemoDataSeeder::class);
    }

    public function test_admin_can_open_every_page(): void
    {
        $admin = User::query()->where('email', 'admin@school.test')->firstOrFail();
        $section = Section::query()->whereHas('term', fn ($q) => $q->where('is_current', true))->firstOrFail();
        $student = Student::query()->firstOrFail();

        $pages = [
            route('dashboard'),
            route('account'),
            route('students.index'),
            route('students.create'),
            route('students.show', $student),
            route('students.edit', $student),
            route('courses.index'),
            route('courses.create'),
            route('courses.edit', Course::query()->firstOrFail()),
            route('terms.index'),
            route('terms.create'),
            route('terms.edit', AcademicTerm::query()->firstOrFail()),
            route('sections.index'),
            route('sections.create'),
            route('sections.show', $section),
            route('sections.edit', $section),
            route('sections.gradebook', $section),
            route('sections.attendance', $section),
            route('attendance.index'),
            route('users.index'),
            route('users.create'),
            route('users.edit', $admin),
        ];

        foreach ($pages as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_teacher_pages_and_restrictions(): void
    {
        $teacher = User::query()->where('email', 'teacher@school.test')->firstOrFail();
        $own = $teacher->taughtSections()->firstOrFail();
        $other = Section::query()->where('teacher_id', '!=', $teacher->id)->firstOrFail();

        foreach ([route('dashboard'), route('students.index'), route('sections.index'), route('sections.show', $own),
            route('sections.gradebook', $own), route('sections.attendance', $own), route('attendance.index'), route('courses.index')] as $url) {
            $this->actingAs($teacher)->get($url)->assertOk();
        }

        foreach ([route('sections.gradebook', $other), route('sections.attendance', $other), route('students.create'),
            route('courses.create'), route('terms.index'), route('users.index'), route('sections.create')] as $url) {
            $this->actingAs($teacher)->get($url)->assertForbidden();
        }
    }

    public function test_student_pages_and_restrictions(): void
    {
        $user = User::query()->where('email', 'student@school.test')->firstOrFail();
        $own = $user->student->enrollments()->firstOrFail()->section;
        $otherStudent = Student::query()->whereKeyNot($user->student->id)->firstOrFail();

        foreach ([route('dashboard'), route('students.show', $user->student), route('sections.index'),
            route('sections.show', $own), route('attendance.index'), route('courses.index')] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $this->actingAs($user)->get(route('sections.show', $own))->assertSee('My grades')->assertDontSee('Class roster');

        foreach ([route('students.index'), route('students.show', $otherStudent), route('sections.gradebook', $own),
            route('sections.attendance', $own), route('users.index'), route('terms.index')] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }
}
