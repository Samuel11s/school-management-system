<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\SectionStatus;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class/section: one offering of a course in an academic term, taught by a teacher.
 *
 * @property int|null $enrolled_count
 */
#[Fillable(['course_id', 'academic_term_id', 'teacher_id', 'code', 'room', 'schedule', 'capacity', 'status'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => SectionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /**
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Enrollments currently holding a seat.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->whereIn('status', EnrollmentStatus::seatHolding());
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Display name such as "MATH101-A".
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => ($this->course?->code ?? 'SECTION').'-'.$this->code);
    }

    public function seatsTaken(): int
    {
        return $this->enrolled_count ?? $this->activeEnrollments()->count();
    }

    public function seatsAvailable(): int
    {
        return max(0, $this->capacity - $this->seatsTaken());
    }

    public function isFull(): bool
    {
        return $this->seatsAvailable() === 0;
    }

    public function isTaughtBy(User $user): bool
    {
        return $this->teacher_id !== null && $this->teacher_id === $user->id;
    }

    /**
     * @param  Builder<Section>  $query
     */
    public function scopeWithEnrolledCount(Builder $query): void
    {
        $query->withCount(['enrollments as enrolled_count' => fn (Builder $q) => $q->whereIn('status', EnrollmentStatus::seatHolding())]);
    }

    /**
     * Restrict the query to sections the given user may see in detail.
     *
     * @param  Builder<Section>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isTeacher()) {
            $query->where('teacher_id', $user->id);

            return;
        }

        $query->whereHas('enrollments.student', fn (Builder $q) => $q->where('user_id', $user->id));
    }
}
