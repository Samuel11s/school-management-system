<?php

namespace App\Notifications;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public readonly Enrollment $enrollment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $section = $this->enrollment->section;
        $course = $section->course;

        $message = (new MailMessage)
            ->subject("Enrollment update: {$course->code} {$course->title}");

        return $this->enrollment->status === EnrollmentStatus::Enrolled
            ? $message
                ->line("You have been enrolled in {$course->title} (class {$section->name}) for {$section->term->name}.")
                ->action('View my classes', route('dashboard'))
            : $message
                ->line("Your enrollment in {$course->title} (class {$section->name}) has been dropped.")
                ->line('Please contact the school office if you believe this is a mistake.');
    }
}
