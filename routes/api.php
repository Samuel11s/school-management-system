<?php

use App\Http\Controllers\Api\V1\AcademicTermController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Token authenticated (Laravel Sanctum) and rate limited. Authorization for
| every record is enforced by the same policies used by the web interface.
| Documentation: /docs/api (OpenAPI JSON at /docs/api.json).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/token', [AuthController::class, 'store'])
        ->middleware('throttle:api-login')
        ->name('auth.token');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'show'])->name('auth.me');
        Route::delete('auth/token', [AuthController::class, 'destroy'])->name('auth.logout');

        Route::apiResource('students', StudentController::class);
        Route::get('students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');

        Route::apiResource('courses', CourseController::class);
        Route::apiResource('terms', AcademicTermController::class);

        Route::apiResource('sections', SectionController::class);
        Route::get('sections/{section}/grade-summary', [SectionController::class, 'gradeSummary'])->name('sections.grade-summary');
        Route::get('sections/{section}/attendance-summary', [SectionController::class, 'attendanceSummary'])->name('sections.attendance-summary');
        Route::post('sections/{section}/attendance', [AttendanceController::class, 'register'])->name('sections.attendance');

        Route::apiResource('enrollments', EnrollmentController::class)->only(['index', 'store', 'show']);
        Route::post('enrollments/{enrollment}/drop', [EnrollmentController::class, 'drop'])->name('enrollments.drop');
        Route::post('enrollments/{enrollment}/complete', [EnrollmentController::class, 'complete'])->name('enrollments.complete');

        Route::apiResource('assessments', AssessmentController::class);
        Route::apiResource('grades', GradeController::class);

        Route::apiResource('attendance', AttendanceController::class)->parameters(['attendance' => 'attendanceRecord']);
    });
});
