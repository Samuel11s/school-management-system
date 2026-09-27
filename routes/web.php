<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\StudentPhotoController;
use App\Livewire\Attendance;
use App\Livewire\Courses;
use App\Livewire\Dashboard;
use App\Livewire\Grades;
use App\Livewire\Sections;
use App\Livewire\Students;
use App\Livewire\Terms;
use App\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
| Every page requires an authenticated, active account. Authorization for
| each record is enforced by policies inside the components themselves.
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/account', AccountController::class)->name('account');

    Route::get('/students', Students\Index::class)->name('students.index');
    Route::get('/students/create', Students\Form::class)->name('students.create');
    Route::get('/students/{student}', Students\Show::class)->name('students.show');
    Route::get('/students/{student}/edit', Students\Form::class)->name('students.edit');
    Route::get('/students/{student}/photo', StudentPhotoController::class)->name('students.photo');

    Route::get('/courses', Courses\Index::class)->name('courses.index');
    Route::get('/courses/create', Courses\Form::class)->name('courses.create');
    Route::get('/courses/{course}/edit', Courses\Form::class)->name('courses.edit');

    Route::get('/terms', Terms\Index::class)->name('terms.index');
    Route::get('/terms/create', Terms\Form::class)->name('terms.create');
    Route::get('/terms/{term}/edit', Terms\Form::class)->name('terms.edit');

    Route::get('/classes', Sections\Index::class)->name('sections.index');
    Route::get('/classes/create', Sections\Form::class)->name('sections.create');
    Route::get('/classes/{section}', Sections\Show::class)->name('sections.show');
    Route::get('/classes/{section}/edit', Sections\Form::class)->name('sections.edit');
    Route::get('/classes/{section}/gradebook', Grades\Gradebook::class)->name('sections.gradebook');
    Route::get('/classes/{section}/attendance', Attendance\Register::class)->name('sections.attendance');

    Route::get('/attendance', Attendance\Report::class)->name('attendance.index');

    Route::get('/users', Users\Index::class)->name('users.index');
    Route::get('/users/create', Users\Form::class)->name('users.create');
    Route::get('/users/{user}/edit', Users\Form::class)->name('users.edit');
});
