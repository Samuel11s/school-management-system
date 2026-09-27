<?php

namespace App\Listeners;

use App\Events\EnrollmentDropped;
use App\Events\StudentEnrolled;
use App\Notifications\EnrollmentStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Emails the student (when they have an account) about enrollment changes
 * and writes an audit log entry. Runs on the queue.
 */
class NotifyStudentOfEnrollmentChange implements ShouldQueue
{
    public int $tries = 3;

    public function handle(StudentEnrolled|EnrollmentDropped $event): void
    {
        $enrollment = $event->enrollment->loadMissing(['student.user', 'section.course', 'section.term']);

        // Identifiers only: no names, emails or other personal data in logs.
        Log::info('enrollment.status_changed', [
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'section_id' => $enrollment->section_id,
            'status' => $enrollment->status->value,
        ]);

        $enrollment->student?->user?->notify(new EnrollmentStatusChanged($enrollment));
    }
}
