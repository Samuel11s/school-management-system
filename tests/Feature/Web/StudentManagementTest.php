<?php

namespace Tests\Feature\Web;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Livewire\Students\Form;
use App\Livewire\Students\Index;
use App\Livewire\Students\Show;
use App\Models\Student;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_admin_can_search_and_filter_students(): void
    {
        $this->activeStudent(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'grade_level' => 10]);
        $this->activeStudent(['first_name' => 'Grace', 'last_name' => 'Hopper', 'status' => StudentStatus::Graduated, 'grade_level' => 12]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Lovelace')->assertSee('Hopper')
            ->set('search', 'love')
            ->assertSee('Lovelace')->assertDontSee('Hopper')
            ->set('search', '')
            ->set('status', 'graduated')
            ->assertSee('Hopper')->assertDontSee('Lovelace')
            ->call('resetFilters')
            ->set('gradeLevel', '10')
            ->assertSee('Lovelace')->assertDontSee('Hopper');
    }

    public function test_listing_is_paginated_and_sortable(): void
    {
        Student::factory()->count(20)->create();
        $first = $this->activeStudent(['last_name' => 'Aaronson']);
        $last = $this->activeStudent(['last_name' => 'Zzyzx']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Aaronson')->assertDontSee('Zzyzx')
            ->call('sortBy', 'last_name')
            ->assertSee('Zzyzx')->assertDontSee('Aaronson')
            ->call('sortBy', 'password') // not sortable: ignored
            ->assertSet('sortField', 'last_name');
    }

    public function test_teachers_only_see_their_own_students(): void
    {
        $teacher = $this->teacher();
        $mine = $this->activeStudent(['last_name' => 'Mine']);
        $theirs = $this->activeStudent(['last_name' => 'Theirs']);
        $this->enroll($mine, $this->section(teacher: $teacher));
        $this->enroll($theirs, $this->section());

        Livewire::actingAs($teacher)->test(Index::class)->assertSee('Mine')->assertDontSee('Theirs');

        $this->actingAs($teacher)->get(route('students.show', $mine))->assertOk()->assertDontSee($mine->phone);
        $this->actingAs($teacher)->get(route('students.show', $theirs))->assertForbidden();
    }

    public function test_students_can_only_view_their_own_profile(): void
    {
        $student = $this->activeStudent(withAccount: true);
        $other = $this->activeStudent();

        $this->actingAs($student->user)->get(route('students.show', $student))->assertOk()->assertSee($student->phone);
        $this->actingAs($student->user)->get(route('students.show', $other))->assertForbidden();
        $this->actingAs($student->user)->get(route('students.index'))->assertForbidden();
    }

    public function test_admin_can_create_a_student_with_login_account(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin())
            ->test(Form::class)
            ->set('first_name', 'Marie')
            ->set('last_name', 'Curie')
            ->set('email', 'Marie.Curie@Example.com')
            ->set('grade_level', '11')
            ->set('admission_date', '2026-09-01')
            ->set('create_account', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $student = Student::query()->where('email', 'marie.curie@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^S2026-\d{4}$/', $student->student_number);
        $this->assertTrue($student->user->isStudent());
        $this->assertDatabaseHas('status_histories', ['subject_id' => $student->id, 'to_status' => 'active', 'reason' => 'Record created']);
        Notification::assertSentTo($student->user, ResetPassword::class);
    }

    public function test_student_numbers_are_sequential_per_admission_year(): void
    {
        $a = $this->activeStudent(['admission_date' => '2025-09-01']);
        $b = $this->activeStudent(['admission_date' => '2025-10-01']);
        $c = $this->activeStudent(['admission_date' => '2026-01-10']);

        $this->assertSame('S2025-0001', $a->student_number);
        $this->assertSame('S2025-0002', $b->student_number);
        $this->assertSame('S2026-0001', $c->student_number);
    }

    public function test_validation_errors_are_reported(): void
    {
        $existing = $this->activeStudent();

        Livewire::actingAs($this->admin())
            ->test(Form::class)
            ->set('first_name', '')
            ->set('email', $existing->email)
            ->set('phone', '<script>')
            ->set('date_of_birth', now()->addDay()->toDateString())
            ->set('grade_level', '14')
            ->set('status', 'expelled')
            ->call('save')
            ->assertHasErrors([
                'first_name' => 'required',
                'email' => 'unique',
                'phone' => 'regex',
                'date_of_birth' => 'before',
                'grade_level' => 'between',
                'status',
            ]);

        $this->assertSame(1, Student::query()->count());
    }

    public function test_photo_uploads_are_validated_and_stored_privately(): void
    {
        Storage::fake('local');
        $student = $this->activeStudent();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Form::class, ['student' => $student])
            ->set('photo', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
            ->assertHasErrors('photo');

        Livewire::actingAs($admin)
            ->test(Form::class, ['student' => $student])
            ->set('photo', UploadedFile::fake()->image('big.jpg')->size(5000))
            ->assertHasErrors('photo');

        Livewire::actingAs($admin)
            ->test(Form::class, ['student' => $student])
            ->set('photo', UploadedFile::fake()->image('me.png', 200, 200))
            ->call('save')
            ->assertHasNoErrors();

        $path = $student->fresh()->photo_path;
        $this->assertStringStartsWith('student-photos/', $path);
        $this->assertStringNotContainsString('me.png', $path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($admin)->get(route('students.photo', $student))->assertOk();
        $this->actingAs($this->studentUser())->get(route('students.photo', $student))->assertForbidden();
    }

    public function test_status_change_is_recorded_with_reason_and_withdrawal_drops_enrollments(): void
    {
        $student = $this->activeStudent();
        $enrollment = $this->enroll($student, $this->section());

        Livewire::actingAs($this->admin())
            ->test(Form::class, ['student' => $student])
            ->set('status', 'withdrawn')
            ->set('status_reason', 'Family relocation')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(StudentStatus::Withdrawn, $student->fresh()->status);
        $this->assertDatabaseHas('status_histories', ['subject_id' => $student->id, 'from_status' => 'active', 'to_status' => 'withdrawn', 'reason' => 'Family relocation']);
        $this->assertSame(EnrollmentStatus::Dropped, $enrollment->fresh()->status);
    }

    public function test_deleting_a_student_soft_deletes_drops_enrollments_and_disables_login(): void
    {
        $student = $this->activeStudent(withAccount: true);
        $enrollment = $this->enroll($student, $this->section());

        Livewire::actingAs($this->admin())->test(Show::class, ['student' => $student])->call('delete')->assertRedirect(route('students.index'));

        $this->assertSoftDeleted($student);
        $this->assertSame(EnrollmentStatus::Dropped, $enrollment->fresh()->status);
        $this->assertFalse($student->user->fresh()->is_active);
        $this->actingAs($this->admin())->get(route('students.show', $student))->assertNotFound();
    }

    public function test_teachers_cannot_create_update_or_delete_students(): void
    {
        $teacher = $this->teacher();
        $student = $this->activeStudent();
        $this->enroll($student, $this->section(teacher: $teacher));

        $this->actingAs($teacher)->get(route('students.create'))->assertForbidden();
        $this->actingAs($teacher)->get(route('students.edit', $student))->assertForbidden();

        Livewire::actingAs($teacher)->test(Index::class)->call('delete', $student->id)->assertForbidden();
        $this->assertNotSoftDeleted($student);
    }

    public function test_admin_cannot_delete_hidden_or_missing_student_ids(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)->call('delete', 999999)->assertNotFound();
    }

    public function test_output_is_escaped(): void
    {
        $this->activeStudent(['first_name' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin())->get(route('students.index'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_retention_prunes_old_soft_deleted_students(): void
    {
        config(['school.retention.deleted_students_days' => 30]);
        $old = $this->activeStudent();
        $recent = $this->activeStudent();
        $this->enroll($old, $this->section());
        $old->delete();
        $recent->delete();
        Student::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(31)]);

        $this->artisan('model:prune', ['--model' => [Student::class]])->assertSuccessful();

        $this->assertDatabaseMissing('students', ['id' => $old->id]);
        $this->assertDatabaseMissing('enrollments', ['student_id' => $old->id]);
        $this->assertSoftDeleted($recent);
    }
}
