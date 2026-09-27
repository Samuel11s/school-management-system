<?php

namespace Database\Seeders;

use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\GradebookService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo data for local development and evaluation. All accounts use the
 * password "password". Never run in production.
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly GradebookService $gradebook,
    ) {}

    public function run(): void
    {
        mt_srand(2026);
        fake()->seed(2026);

        User::factory()->admin()->create([
            'name' => 'Ada Administrator',
            'email' => 'admin@school.test',
        ]);

        $teachers = collect([
            ['Grace Hopper', 'teacher@school.test'],
            ['Alan Turing', 'alan.turing@school.test'],
            ['Katherine Johnson', 'katherine.johnson@school.test'],
            ['Carl Sagan', 'carl.sagan@school.test'],
        ])->map(fn ($t) => User::factory()->teacher()->create(['name' => $t[0], 'email' => $t[1]]));

        [$pastTerm, $currentTerm] = $this->terms();
        $courses = $this->courses();
        $students = $this->students();

        $this->pastTermHistory($pastTerm, $courses, $teachers, $students);
        $sections = $this->currentSections($currentTerm, $courses, $teachers);
        $this->enrollCurrentTerm($sections, $students);
        $this->assessmentsAndGrades($sections);
        $this->attendance($sections, $currentTerm);
    }

    /**
     * @return array{0: AcademicTerm, 1: AcademicTerm}
     */
    private function terms(): array
    {
        $today = Carbon::today();
        $currentStart = $today->copy()->subWeeks(4)->startOfWeek();
        $pastStart = $currentStart->copy()->subMonths(6);

        $past = AcademicTerm::create([
            'name' => $this->termName($pastStart),
            'code' => $this->termCode($pastStart),
            'starts_on' => $pastStart,
            'ends_on' => $pastStart->copy()->addMonths(4),
            'enrollment_opens_on' => $pastStart->copy()->subMonth(),
            'enrollment_closes_on' => $pastStart->copy()->addWeeks(2),
            'is_current' => false,
        ]);

        $current = AcademicTerm::create([
            'name' => $this->termName($currentStart),
            'code' => $this->termCode($currentStart),
            'starts_on' => $currentStart,
            'ends_on' => $currentStart->copy()->addMonths(4),
            'enrollment_opens_on' => $currentStart->copy()->subMonth(),
            'enrollment_closes_on' => $today->copy()->addWeeks(3),
            'is_current' => true,
        ]);

        return [$past, $current];
    }

    private function termName(Carbon $start): string
    {
        return ($start->month >= 7 ? 'Fall' : 'Spring').' '.$start->year;
    }

    private function termCode(Carbon $start): string
    {
        return ($start->month >= 7 ? 'FA' : 'SP').$start->year;
    }

    /**
     * @return Collection<string, Course>
     */
    private function courses(): Collection
    {
        $definitions = [
            ['MATH101', 'Algebra I', 'Mathematics', 4, []],
            ['MATH201', 'Algebra II', 'Mathematics', 4, ['MATH101']],
            ['ENG101', 'English Composition', 'Languages', 3, []],
            ['SCI101', 'General Science', 'Science', 4, []],
            ['SCI201', 'Biology', 'Science', 4, ['SCI101']],
            ['HIS101', 'World History', 'Humanities', 3, []],
            ['CS101', 'Introduction to Programming', 'Technology', 3, []],
            ['CS201', 'Data Structures', 'Technology', 3, ['CS101']],
            ['ART101', 'Visual Arts', 'Arts', 2, []],
        ];

        $courses = collect();

        foreach ($definitions as [$code, $title, $department, $credits, $prerequisites]) {
            $course = Course::create([
                'code' => $code,
                'title' => $title,
                'department' => $department,
                'credits' => $credits,
                'description' => "{$title} covers the core concepts of the subject through lectures, practice and projects.",
                'is_active' => true,
            ]);
            $course->prerequisites()->sync($courses->only($prerequisites)->pluck('id'));
            $courses->put($code, $course);
        }

        return $courses;
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(): Collection
    {
        $students = collect([
            Student::factory()->withAccount()->create([
                'first_name' => 'Sam',
                'last_name' => 'Student',
                'email' => 'student@school.test',
                'grade_level' => 10,
            ]),
        ]);

        $students = $students->merge(Student::factory()->withAccount()->count(35)->create());
        $students = $students->merge(Student::factory()->count(4)->create());

        Student::factory()->status(StudentStatus::Graduated)->count(3)->create();
        Student::factory()->status(StudentStatus::Suspended)->create();
        Student::factory()->status(StudentStatus::Withdrawn)->create();

        return $students;
    }

    /**
     * Completed enrollments in the previous term so that prerequisites and
     * transcripts have data.
     *
     * @param  Collection<string, Course>  $courses
     * @param  Collection<int, User>  $teachers
     * @param  Collection<int, Student>  $students
     */
    private function pastTermHistory(AcademicTerm $term, Collection $courses, Collection $teachers, Collection $students): void
    {
        $calculator = $this->gradebook->calculator();

        foreach (['MATH101', 'SCI101', 'CS101', 'ENG101'] as $i => $code) {
            $section = Section::create([
                'course_id' => $courses[$code]->id,
                'academic_term_id' => $term->id,
                'teacher_id' => $teachers[$i % $teachers->count()]->id,
                'code' => 'A',
                'room' => 'Room '.(101 + $i),
                'schedule' => 'Mon/Wed 09:00-10:30',
                'capacity' => 40,
                'status' => 'closed',
            ]);

            $demoStudent = $students->first();
            $cohort = $students->random(24)->push($demoStudent)->unique('id');

            foreach ($cohort as $student) {
                $score = $student->is($demoStudent) ? 88.5 : round(mt_rand(4500, 9900) / 100, 2);

                Enrollment::create([
                    'student_id' => $student->id,
                    'section_id' => $section->id,
                    'status' => EnrollmentStatus::Completed,
                    'enrolled_at' => $term->starts_on->copy()->subWeeks(2),
                    'completed_at' => $term->ends_on,
                    'final_score' => $score,
                    'letter_grade' => $calculator->letterFor($score),
                ]);
            }
        }
    }

    /**
     * @param  Collection<string, Course>  $courses
     * @param  Collection<int, User>  $teachers
     * @return Collection<int, Section>
     */
    private function currentSections(AcademicTerm $term, Collection $courses, Collection $teachers): Collection
    {
        $schedules = ['Mon/Wed 08:00-09:30', 'Mon/Wed 10:00-11:30', 'Tue/Thu 08:00-09:30', 'Tue/Thu 10:00-11:30', 'Fri 09:00-12:00'];
        $sections = collect();
        $i = 0;

        foreach ($courses as $course) {
            $count = in_array($course->code, ['MATH101', 'ENG101'], true) ? 2 : 1;

            for ($n = 0; $n < $count; $n++) {
                $sections->push(Section::create([
                    'course_id' => $course->id,
                    'academic_term_id' => $term->id,
                    'teacher_id' => $teachers[$i % $teachers->count()]->id,
                    'code' => chr(65 + $n),
                    'room' => 'Room '.(201 + $i),
                    'schedule' => $schedules[$i % count($schedules)],
                    // Keep one small class so the "class full" rule is visible in the demo.
                    'capacity' => $course->code === 'ART101' ? 8 : 25,
                    'status' => 'open',
                ]));
                $i++;
            }
        }

        return $sections;
    }

    /**
     * Enroll through the service so the demo data obeys every business rule.
     *
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, Student>  $students
     */
    private function enrollCurrentTerm(Collection $sections, Collection $students): void
    {
        foreach ($students as $index => $student) {
            $wanted = $index === 0
                ? $sections->filter(fn (Section $s) => in_array($s->name, ['MATH201-A', 'ENG101-A', 'SCI101-A', 'CS201-A'], true))
                : $sections->shuffle()->take(5);

            foreach ($wanted as $section) {
                try {
                    $this->enrollments->enroll($student, $section, 'Demo enrollment');
                } catch (DomainRuleException) {
                    // Ineligible (prerequisite, duplicate course or full class): skip, as a registrar would.
                }
            }
        }

        // Make sure the demo student has passed the prerequisites of their classes.
        $demo = $students->first();

        if ($demo->enrollments()->active()->count() < 2) {
            foreach ($sections->filter(fn (Section $s) => $s->course->prerequisites->isEmpty())->take(3) as $section) {
                rescue(fn () => $this->enrollments->enroll($demo, $section, 'Demo enrollment'), report: false);
            }
        }
    }

    /**
     * @param  Collection<int, Section>  $sections
     */
    private function assessmentsAndGrades(Collection $sections): void
    {
        $plan = [
            ['Quiz 1', AssessmentType::Quiz, 20, 10, -14],
            ['Assignment 1', AssessmentType::Assignment, 50, 20, -7],
            ['Midterm Exam', AssessmentType::Exam, 100, 30, 21],
            ['Final Project', AssessmentType::Project, 100, 40, 56],
        ];

        foreach ($sections as $section) {
            foreach ($plan as [$title, $type, $max, $weight, $dueInDays]) {
                $assessment = $this->gradebook->saveAssessment($section, [
                    'title' => $title,
                    'type' => $type,
                    'max_score' => $max,
                    'weight' => $weight,
                    'due_on' => Carbon::today()->addDays($dueInDays)->toDateString(),
                ]);

                if ($dueInDays > 0) {
                    continue;
                }

                $entries = $section->activeEnrollments()->pluck('id')
                    ->mapWithKeys(fn ($id) => [$id => ['score' => round($max * mt_rand(55, 100) / 100, 1)]])
                    ->all();

                $this->gradebook->recordGrades($assessment, $entries, $section->teacher);
            }
        }
    }

    /**
     * Attendance for the most recent school days of the term.
     *
     * @param  Collection<int, Section>  $sections
     */
    private function attendance(Collection $sections, AcademicTerm $term): void
    {
        $days = collect();
        $day = Carbon::yesterday();

        while ($days->count() < 10 && $day->gte($term->starts_on)) {
            if ($day->isWeekday()) {
                $days->push($day->copy());
            }
            $day->subDay();
        }

        $statuses = [
            ...array_fill(0, 16, AttendanceStatus::Present->value),
            AttendanceStatus::Late->value,
            AttendanceStatus::Late->value,
            AttendanceStatus::Absent->value,
            AttendanceStatus::Excused->value,
        ];

        foreach ($sections as $section) {
            $studentIds = $section->activeEnrollments()->pluck('student_id');
            $rows = [];

            foreach ($days as $date) {
                foreach ($studentIds as $studentId) {
                    $rows[] = [
                        'section_id' => $section->id,
                        'student_id' => $studentId,
                        'attended_on' => $date->toDateString(),
                        'status' => $statuses[array_rand($statuses)],
                        'recorded_by' => $section->teacher_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                AttendanceRecord::insert($chunk);
            }
        }
    }
}
