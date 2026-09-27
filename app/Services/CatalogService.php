<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\SectionStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Course catalog, academic terms and classes (sections): persistence plus
 * the integrity rules that go beyond field validation.
 */
final class CatalogService
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /**
     * @param  array<string, mixed>  $data  validated attributes
     * @param  list<int>  $prerequisiteIds
     *
     * @throws DomainRuleException
     */
    public function saveCourse(array $data, array $prerequisiteIds = [], ?Course $course = null): Course
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $prerequisiteIds = array_values(array_unique(array_map('intval', $prerequisiteIds)));

        if ($course !== null) {
            $this->assertNoPrerequisiteCycle($course, $prerequisiteIds);
        }

        return DB::transaction(function () use ($data, $prerequisiteIds, $course) {
            $course ??= new Course;
            $course->fill($data)->save();
            $course->prerequisites()->sync($prerequisiteIds);

            return $course;
        });
    }

    /**
     * @throws DomainRuleException
     */
    public function deleteCourse(Course $course): void
    {
        if ($course->sections()->exists()) {
            throw new DomainRuleException('This course has classes and cannot be deleted. Mark it inactive instead.', 'course');
        }

        $course->delete();
    }

    /**
     * @param  array<string, mixed>  $data  validated attributes
     */
    public function saveTerm(array $data, ?AcademicTerm $term = null): AcademicTerm
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $term ??= new AcademicTerm;
        $term->fill($data)->save();

        return $term;
    }

    /**
     * @throws DomainRuleException
     */
    public function deleteTerm(AcademicTerm $term): void
    {
        if ($term->sections()->exists()) {
            throw new DomainRuleException('This term has classes and cannot be deleted.', 'term');
        }

        $term->delete();
    }

    /**
     * @param  array<string, mixed>  $data  validated attributes
     *
     * @throws DomainRuleException
     */
    public function saveSection(array $data, ?Section $section = null): Section
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }


        return DB::transaction(function () use ($data, $section) {
            if ($section !== null) {
                $enrolled = $section->activeEnrollments()->count();

                if ((int) $data['capacity'] < $enrolled) {
                    throw new DomainRuleException(
                        "Capacity cannot be lower than the {$enrolled} students already enrolled.",
                        'capacity',
                    );
                }
            }

            $section ??= new Section;
            $wasCancelled = $section->status === SectionStatus::Cancelled;
            $section->fill($data)->save();

            // Cancelling a class releases every student from it.
            if (! $wasCancelled && $section->status === SectionStatus::Cancelled) {
                $section->enrollments()
                    ->where('status', EnrollmentStatus::Enrolled->value)
                    ->get()
                    ->each(fn (Enrollment $e) => $this->enrollments->drop($e, 'Class cancelled'));
            }

            return $section;
        });
    }

    /**
     * @throws DomainRuleException
     */
    public function deleteSection(Section $section): void
    {
        if ($section->enrollments()->exists()) {
            throw new DomainRuleException('This class has enrollment records and cannot be deleted. Cancel it instead.', 'section');
        }

        $section->delete();
    }

    /**
     * Reject prerequisite graphs where a course (directly or transitively) requires itself.
     *
     * @param  list<int>  $prerequisiteIds
     *
     * @throws DomainRuleException
     */
    private function assertNoPrerequisiteCycle(Course $course, array $prerequisiteIds): void
    {
        if (in_array($course->id, $prerequisiteIds, true)) {
            throw new DomainRuleException('A course cannot be its own prerequisite.', 'prerequisite_ids');
        }

        $visited = [];
        $queue = $prerequisiteIds;

        while ($queue !== []) {
            $id = array_shift($queue);

            if (isset($visited[$id])) {
                continue;
            }

            $visited[$id] = true;

            $next = DB::table('course_prerequisites')->where('course_id', $id)->pluck('prerequisite_id')->map(fn ($v) => (int) $v)->all();

            if (in_array($course->id, $next, true)) {
                throw new DomainRuleException('These prerequisites would create a circular dependency.', 'prerequisite_ids');
            }

            array_push($queue, ...$next);
        }
    }
}
