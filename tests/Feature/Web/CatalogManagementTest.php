<?php

namespace Tests\Feature\Web;

use App\Enums\EnrollmentStatus;
use App\Livewire\Courses;
use App\Livewire\Sections;
use App\Livewire\Terms;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_admin_can_create_a_course_with_prerequisites(): void
    {
        $intro = Course::factory()->create(['code' => 'BIO101']);

        Livewire::actingAs($this->admin())
            ->test(Courses\Form::class)
            ->set('code', 'bio201')
            ->set('title', 'Genetics')
            ->set('credits', 4)
            ->set('prerequisite_ids', [$intro->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('courses.index'));

        $course = Course::query()->where('code', 'BIO201')->firstOrFail();
        $this->assertTrue($course->prerequisites->contains($intro));
    }

    public function test_course_codes_are_unique_and_validated(): void
    {
        Course::factory()->create(['code' => 'MATH101']);

        Livewire::actingAs($this->admin())
            ->test(Courses\Form::class)
            ->set('code', 'MATH101')
            ->set('title', '')
            ->set('credits', 99)
            ->call('save')
            ->assertHasErrors(['code' => 'unique', 'title' => 'required', 'credits' => 'between']);
    }

    public function test_circular_prerequisites_are_rejected(): void
    {
        $a = Course::factory()->create();
        $b = Course::factory()->create();
        $b->prerequisites()->attach($a);

        Livewire::actingAs($this->admin())
            ->test(Courses\Form::class, ['course' => $a])
            ->set('prerequisite_ids', [$b->id])
            ->call('save')
            ->assertHasErrors('prerequisite_ids');

        $this->assertTrue($a->prerequisites()->doesntExist());
    }

    public function test_course_with_classes_cannot_be_deleted(): void
    {
        $used = $this->section()->course;
        $unused = Course::factory()->create();

        Livewire::actingAs($this->admin())->test(Courses\Index::class)
            ->call('delete', $used->id)
            ->call('delete', $unused->id);

        $this->assertNotSoftDeleted($used);
        $this->assertSoftDeleted($unused);
    }

    public function test_only_admins_manage_the_catalog(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();

        $this->actingAs($teacher)->get(route('courses.index'))->assertOk();
        $this->actingAs($teacher)->get(route('courses.create'))->assertForbidden();
        $this->actingAs($teacher)->get(route('courses.edit', $course))->assertForbidden();
        Livewire::actingAs($teacher)->test(Courses\Index::class)->call('delete', $course->id)->assertForbidden();
    }

    public function test_term_dates_are_validated_and_only_one_term_is_current(): void
    {
        $old = AcademicTerm::factory()->current()->create();

        Livewire::actingAs($this->admin())
            ->test(Terms\Form::class)
            ->set('name', 'Spring 2027')
            ->set('code', 'SP2027')
            ->set('starts_on', '2027-01-10')
            ->set('ends_on', '2027-01-01')
            ->set('enrollment_opens_on', '2026-12-01')
            ->set('enrollment_closes_on', '2027-02-01')
            ->call('save')
            ->assertHasErrors(['ends_on' => 'after'])
            ->set('ends_on', '2027-05-30')
            ->set('is_current', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($old->fresh()->is_current);
        $this->assertTrue(AcademicTerm::query()->where('code', 'SP2027')->value('is_current'));
    }

    public function test_admin_can_create_a_class_and_duplicates_are_rejected(): void
    {
        $term = $this->openTerm();
        $course = Course::factory()->create();
        $teacher = $this->teacher();
        $admin = $this->admin();

        $create = fn () => Livewire::actingAs($admin)
            ->test(Sections\Form::class)
            ->set('course_id', $course->id)
            ->set('academic_term_id', $term->id)
            ->set('teacher_id', $teacher->id)
            ->set('code', 'a')
            ->set('capacity', 20)
            ->call('save');

        $create()->assertHasNoErrors();
        $create()->assertHasErrors(['code' => 'unique']);

        $this->assertSame(1, Section::query()->count());
        $this->assertSame('A', Section::query()->value('code'));
    }

    public function test_class_teacher_must_have_teacher_role(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Sections\Form::class)
            ->set('course_id', Course::factory()->create()->id)
            ->set('academic_term_id', $this->openTerm()->id)
            ->set('teacher_id', $this->studentUser()->id)
            ->set('code', 'A')
            ->call('save')
            ->assertHasErrors('teacher_id');
    }

    public function test_capacity_cannot_drop_below_enrolled_count(): void
    {
        $section = $this->section(['capacity' => 5]);
        $this->enroll($this->activeStudent(), $section);
        $this->enroll($this->activeStudent(), $section);

        Livewire::actingAs($this->admin())
            ->test(Sections\Form::class, ['section' => $section])
            ->set('capacity', 1)
            ->call('save')
            ->assertHasErrors('capacity');

        $this->assertSame(5, $section->fresh()->capacity);
    }

    public function test_cancelling_a_class_drops_its_enrollments(): void
    {
        $section = $this->section();
        $enrollment = $this->enroll($this->activeStudent(), $section);

        Livewire::actingAs($this->admin())
            ->test(Sections\Form::class, ['section' => $section])
            ->set('status', 'cancelled')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(EnrollmentStatus::Dropped, $enrollment->fresh()->status);
    }

    public function test_class_list_is_filtered_and_scoped_for_teachers(): void
    {
        $teacher = $this->teacher();
        $term = $this->openTerm();
        $mine = $this->section(term: $term, teacher: $teacher);
        $other = $this->section(term: $term);

        Livewire::actingAs($teacher)->test(Sections\Index::class)
            ->assertSee($mine->name)->assertDontSee($other->name);

        Livewire::actingAs($this->admin())->test(Sections\Index::class)
            ->assertSee($mine->name)->assertSee($other->name)
            ->set('teacher', (string) $teacher->id)
            ->assertSee($mine->name)->assertDontSee($other->name);
    }
}
