<div>
    <x-page-header :title="$student ? 'Edit '.$student->full_name : 'Add student'"
                   :subtitle="$student ? 'Student number '.$student->student_number : 'A student number is generated automatically.'"
                   :back="$student ? route('students.show', $student) : route('students.index')" />

    <form wire:submit="save" novalidate>
        <div class="row g-4">
            <div class="col-xl-8">
                <section class="card shadow-sm mb-4" aria-labelledby="personal-heading">
                    <div class="card-body">
                        <h2 id="personal-heading" class="h5 mb-3">Personal details</h2>
                        <div class="row">
                            <x-form.input class="col-md-6" name="first_name" label="First name" required autocomplete="off" />
                            <x-form.input class="col-md-6" name="last_name" label="Last name" required autocomplete="off" />
                            <x-form.input class="col-md-6" name="email" type="email" label="Email address" required autocomplete="off"
                                          help="Used as the login for the student account." />
                            <x-form.input class="col-md-6" name="phone" type="tel" label="Phone" autocomplete="off" />
                            <x-form.input class="col-md-6" name="date_of_birth" type="date" label="Date of birth" />
                            <x-form.input class="col-md-6" name="address" label="Address" autocomplete="off" />
                        </div>
                    </div>
                </section>

                <section class="card shadow-sm mb-4" aria-labelledby="guardian-heading">
                    <div class="card-body">
                        <h2 id="guardian-heading" class="h5 mb-3">Guardian</h2>
                        <div class="row">
                            <x-form.input class="col-md-6" name="guardian_name" label="Guardian name" autocomplete="off" />
                            <x-form.input class="col-md-6" name="guardian_phone" type="tel" label="Guardian phone" autocomplete="off" />
                        </div>
                    </div>
                </section>

                <section class="card shadow-sm" aria-labelledby="academic-heading">
                    <div class="card-body">
                        <h2 id="academic-heading" class="h5 mb-3">Academic information</h2>
                        <div class="row">
                            <x-form.select class="col-md-4" name="grade_level" label="Grade level" placeholder="Not set"
                                           :options="collect(range(1, 12))->mapWithKeys(fn ($l) => [$l => 'Grade '.$l])->all()" />
                            <x-form.input class="col-md-4" name="admission_date" type="date" label="Admission date" required />
                            <x-form.select class="col-md-4" name="status" label="Enrollment status" :options="$statuses" required
                                           wire:model.live="status" />
                            @if ($student && $status !== $student->status->value)
                                <x-form.input class="col-12" name="status_reason" label="Reason for status change"
                                              help="Recorded in the student's status history." />
                            @endif
                            <x-form.textarea class="col-12" name="notes" label="Notes" help="Internal notes; not visible to the student." />
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="card shadow-sm mb-4" aria-labelledby="photo-heading">
                    <div class="card-body">
                        <h2 id="photo-heading" class="h5 mb-3">Photo</h2>

                        @if ($photo && ! $errors->has('photo'))
                            <img src="{{ $photo->temporaryUrl() }}" alt="Preview of the new photo" class="rounded avatar-lg mb-3">
                        @elseif ($student?->photo_path)
                            <img src="{{ route('students.photo', $student) }}" alt="Current photo of {{ $student->full_name }}" class="rounded avatar-lg mb-3">
                        @endif

                        <div class="mb-2">
                            <label for="photo" class="form-label">Upload photo</label>
                            <input type="file" id="photo" wire:model="photo" accept="image/png,image/jpeg,image/webp"
                                   @class(['form-control', 'is-invalid' => $errors->has('photo')])
                                   aria-describedby="photo-help @error('photo') photo-error @enderror">
                            <div id="photo-help" class="form-text">JPG, PNG or WebP, up to {{ (int) (config('school.uploads.photo_max_kilobytes') / 1024) }} MB.</div>
                            @error('photo') <div id="photo-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <x-loading target="photo" label="Uploading" />

                        @if ($student?->photo_path)
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removePhoto"
                                    wire:confirm="Remove this photo?">Remove current photo</button>
                        @endif
                    </div>
                </section>

                @unless ($student)
                    <section class="card shadow-sm mb-4" aria-labelledby="account-heading">
                        <div class="card-body">
                            <h2 id="account-heading" class="h5 mb-3">Login account</h2>
                            <x-form.checkbox name="create_account" label="Create a student login account"
                                             help="The student receives an email with a link to set their own password." />
                        </div>
                    </section>
                @endunless

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,photo">
                        <span wire:loading.remove wire:target="save">{{ $student ? 'Save changes' : 'Create student' }}</span>
                        <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving…</span>
                    </button>
                    <a href="{{ $student ? route('students.show', $student) : route('students.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
