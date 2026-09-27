<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\RecordsStatusHistory;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id', 'student_number', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth',
    'address', 'guardian_name', 'guardian_phone', 'grade_level', 'admission_date', 'status', 'notes',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, Prunable, RecordsStatusHistory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'grade_level' => 'integer',
            'status' => StudentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            if (blank($student->student_number)) {
                $student->student_number = static::nextStudentNumber($student->admission_date->year);
            }
        });
    }

    /**
     * Generate the next sequential student number for an admission year, e.g. S2026-0042.
     */
    public static function nextStudentNumber(int $year): string
    {
        $prefix = config('school.student_number_prefix', 'S').$year.'-';

        $last = static::withTrashed()
            ->where('student_number', 'like', $prefix.'%')
            ->orderByDesc('student_number')
            ->value('student_number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Restrict the query to students the given user is allowed to see.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isTeacher()) {
            $query->whereHas('enrollments.section', fn (Builder $q) => $q->where('teacher_id', $user->id));

            return;
        }

        $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $like = '%'.mb_strtolower($term).'%';
            $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                ->orWhereRaw('LOWER(student_number) LIKE ?', [$like]);
        });
    }

    /**
     * Soft-deleted students are permanently removed after the configured
     * retention period (see config/school.php).
     *
     * @return Builder<Student>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where(
            'deleted_at',
            '<=',
            now()->subDays((int) config('school.retention.deleted_students_days', 365)),
        );
    }

    protected function pruning(): void
    {
        if ($this->photo_path) {
            Storage::disk(config('school.uploads.disk'))->delete($this->photo_path);
        }

        $this->attendanceRecords()->delete();
        $this->enrollments()->each(fn (Enrollment $enrollment) => $enrollment->forceDeleteWithHistory());
        $this->statusHistories()->delete();
    }
}
