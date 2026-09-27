<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Student lifecycle: creation (optionally with a login account), updates,
 * photos and removal.
 */
final class StudentService
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /**
     * @param  array<string, mixed>  $data  validated attributes
     */
    public function create(array $data, bool $withAccount = false): Student
    {
        $data['email'] = mb_strtolower($data['email']);

        $student = DB::transaction(function () use ($data, $withAccount) {
            if ($withAccount) {
                $user = User::create([
                    'name' => trim($data['first_name'].' '.$data['last_name']),
                    'email' => $data['email'],
                    // Unusable random password: the student sets their own via the emailed link.
                    'password' => Str::password(32),
                ]);
                $user->assignRole(Role::Student->value);
                $data['user_id'] = $user->id;
            }

            $student = new Student($data);
            $student->withStatusReason('Record created')->save();

            return $student;
        });

        if ($withAccount) {
            Password::broker()->sendResetLink(['email' => $data['email']]);
        }

        return $student;
    }

    /**
     * @param  array<string, mixed>  $data  validated attributes
     */
    public function update(Student $student, array $data, ?string $statusReason = null): Student
    {
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }

        return DB::transaction(function () use ($student, $data, $statusReason) {
            $student->withStatusReason($statusReason)->fill($data)->save();

            $student->user?->forceFill([
                'name' => $student->full_name,
                'email' => $student->email,
            ])->save();

            // A withdrawn student gives up their seats in current classes.
            if ($student->wasChanged('status') && $student->status === StudentStatus::Withdrawn) {
                $this->dropActiveEnrollments($student, 'Student withdrawn');
            }

            return $student;
        });
    }

    /**
     * Soft delete: active enrollments are dropped and the login is disabled.
     * The record is purged permanently after the retention period.
     */
    public function delete(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $this->dropActiveEnrollments($student, 'Student record deleted');
            $student->user?->forceFill(['is_active' => false])->save();
            $student->delete();
        });
    }

    public function updatePhoto(Student $student, UploadedFile $photo): Student
    {
        $disk = Storage::disk(config('school.uploads.disk'));
        $old = $student->photo_path;

        // Random file name: never trust the client supplied name or extension.
        $path = $photo->storeAs(
            config('school.uploads.photo_directory'),
            Str::uuid()->toString().'.'.$photo->extension(),
            config('school.uploads.disk'),
        );

        $student->forceFill(['photo_path' => $path])->save();

        if ($old && $old !== $path) {
            $disk->delete($old);
        }

        return $student;
    }

    public function removePhoto(Student $student): Student
    {
        if ($student->photo_path) {
            Storage::disk(config('school.uploads.disk'))->delete($student->photo_path);
            $student->forceFill(['photo_path' => null])->save();
        }

        return $student;
    }

    private function dropActiveEnrollments(Student $student, string $reason): void
    {
        $student->enrollments()
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->get()
            ->each(fn (Enrollment $enrollment) => $this->enrollments->drop($enrollment, $reason));
    }
}
