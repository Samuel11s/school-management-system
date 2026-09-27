<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Student numbers
    |--------------------------------------------------------------------------
    |
    | Student numbers are generated as <prefix><admission year>-<sequence>,
    | for example S2026-0001.
    |
    */

    'student_number_prefix' => env('STUDENT_NUMBER_PREFIX', 'S'),

    /*
    |--------------------------------------------------------------------------
    | Grading
    |--------------------------------------------------------------------------
    |
    | Minimum percentage for each letter grade (evaluated top-down) and the
    | minimum final score required to pass a course (and so satisfy it as a
    | prerequisite).
    |
    */

    'grading' => [
        'scale' => [
            'A' => 90,
            'B' => 80,
            'C' => 70,
            'D' => 60,
            'F' => 0,
        ],
        'passing_score' => (float) env('GRADING_PASSING_SCORE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    |
    | Students whose attendance rate falls below this percentage are flagged
    | in attendance summaries.
    |
    */

    'attendance' => [
        'warning_threshold' => (float) env('ATTENDANCE_WARNING_THRESHOLD', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Student photos are stored on a private disk and streamed through an
    | authorised route; they are never publicly addressable.
    |
    */

    'uploads' => [
        'disk' => env('STUDENT_PHOTO_DISK', 'local'),
        'photo_directory' => 'student-photos',
        'photo_max_kilobytes' => (int) env('STUDENT_PHOTO_MAX_KB', 2048),
        'photo_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    | Deleted student records are soft-deleted first and permanently purged
    | (together with enrollments, grades, attendance and photos) after this
    | many days by the scheduled `model:prune` command.
    |
    */

    'retention' => [
        'deleted_students_days' => (int) env('STUDENT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'per_page' => 15,
        'max_per_page' => 100,
        'rate_limit' => (int) env('API_RATE_LIMIT', 60),
    ],

];
