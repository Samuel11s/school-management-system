<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\RecordsStatusHistory;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_id', 'section_id', 'status', 'enrolled_at', 'dropped_at', 'completed_at', 'final_score', 'letter_grade'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory, RecordsStatusHistory;

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_at' => 'datetime',
            'dropped_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Enrolled;
    }

    public function forceDeleteWithHistory(): void
    {
        $this->statusHistories()->delete();
        $this->delete();
    }

    /**
     * @param  Builder<Enrollment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EnrollmentStatus::Enrolled->value);
    }

    /**
     * Restrict the query to enrollments the given user may see.
     *
     * @param  Builder<Enrollment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isTeacher()) {
            $query->whereHas('section', fn (Builder $q) => $q->where('teacher_id', $user->id));

            return;
        }

        $query->whereHas('student', fn (Builder $q) => $q->where('user_id', $user->id));
    }
}
