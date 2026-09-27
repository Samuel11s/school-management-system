<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a student photo from the private disk after an authorization check.
 */
class StudentPhotoController extends Controller
{
    public function __invoke(Student $student): StreamedResponse
    {
        Gate::authorize('view', $student);

        $disk = Storage::disk(config('school.uploads.disk'));

        abort_unless($student->photo_path && $disk->exists($student->photo_path), 404);

        return $disk->response($student->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
