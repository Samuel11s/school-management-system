<?php

namespace App\Livewire\Students;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\StudentService;
use App\Validation\StudentRules;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Create and edit student records.
 */
class Form extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public ?Student $student = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public ?string $phone = null;

    public ?string $date_of_birth = null;

    public ?string $address = null;

    public ?string $guardian_name = null;

    public ?string $guardian_phone = null;

    public ?string $grade_level = null;

    public string $admission_date = '';

    public string $status = 'active';

    public ?string $notes = null;

    public ?string $status_reason = null;

    public bool $create_account = true;

    /** @var UploadedFile|null */
    public $photo = null;

    public function mount(?Student $student = null): void
    {
        if ($student?->exists) {
            $this->authorize('update', $student);
            $this->student = $student;
            $this->fill([
                ...$student->only(['first_name', 'last_name', 'email', 'phone', 'address', 'guardian_name', 'guardian_phone', 'notes']),
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'admission_date' => $student->admission_date->toDateString(),
                'grade_level' => $student->grade_level !== null ? (string) $student->grade_level : null,
                'status' => $student->status->value,
            ]);
            $this->create_account = false;

            return;
        }

        $this->authorize('create', Student::class);
        $this->admission_date = now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = StudentRules::rules($this->student);

        if ($this->student === null && $this->create_account) {
            $rules['email'][] = Rule::unique('users', 'email');
        }

        return [
            ...$rules,
            'photo' => StudentRules::photo(),
            'status_reason' => ['nullable', 'string', 'max:255'],
            'create_account' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            ...StudentRules::messages(),
            'email.unique' => 'This email address is already in use.',
        ];
    }

    public function updatedPhoto(): void
    {
        $this->validateOnly('photo');
    }

    public function save(StudentService $students): void
    {
        // Re-check on every request: Livewire actions are separate HTTP calls.
        $this->student
            ? $this->authorize('update', $this->student)
            : $this->authorize('create', Student::class);

        $validated = $this->validate();
        $data = collect($validated)
            ->except(['photo', 'status_reason', 'create_account'])
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();

        $student = $this->student
            ? $students->update($this->student, $data, $validated['status_reason'] ?? null)
            : $students->create($data, $this->create_account);

        if ($this->photo) {
            $students->updatePhoto($student, $this->photo);
        }

        session()->flash('status', $this->student ? 'Student updated.' : 'Student created.');

        $this->redirectRoute('students.show', $student);
    }

    public function removePhoto(StudentService $students): void
    {
        if ($this->student) {
            $this->authorize('update', $this->student);
            $students->removePhoto($this->student);
            $this->dispatch('notify', message: 'Photo removed.');
        }
    }

    public function render(): View
    {
        return view('livewire.students.form', [
            'statuses' => StudentStatus::options(),
        ])->title($this->student ? 'Edit '.$this->student->full_name : 'Add student');
    }
}
