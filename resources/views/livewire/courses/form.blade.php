<div>
    <x-page-header :title="$course ? 'Edit '.$course->code : 'Add course'" :back="route('courses.index')" />

    <form wire:submit="save" novalidate class="card shadow-sm" style="max-width: 52rem">
        <div class="card-body">
            <div class="row">
                <x-form.input class="col-md-4" name="code" label="Course code" required help="For example MATH101." />
                <x-form.input class="col-md-8" name="title" label="Title" required />
                <x-form.input class="col-md-6" name="department" label="Department" />
                <x-form.input class="col-md-3" name="credits" type="number" min="0" max="20" label="Credits" required />
                <div class="col-md-3 d-flex align-items-end">
                    <x-form.checkbox name="is_active" label="Active" help="Inactive courses stay on record." />
                </div>
                <x-form.textarea class="col-12" name="description" label="Description" rows="4" />

                <fieldset class="col-12 mb-3">
                    <legend class="form-label fs-6">Prerequisites</legend>
                    <p class="form-text mt-0">Students must have passed these courses before enrolling.</p>
                    @if ($availablePrerequisites->isEmpty())
                        <p class="small text-body-secondary">No other courses exist yet.</p>
                    @else
                        <div class="row">
                            @foreach ($availablePrerequisites as $option)
                                <div class="col-sm-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="prereq-{{ $option->id }}"
                                               value="{{ $option->id }}" wire:model="prerequisite_ids">
                                        <label class="form-check-label" for="prereq-{{ $option->id }}">
                                            {{ $option->code }} <span class="text-body-secondary small">{{ $option->title }}</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @error('prerequisite_ids') <div class="text-danger small mt-1" role="alert">{{ $message }}</div> @enderror
                    @error('prerequisite_ids.*') <div class="text-danger small mt-1" role="alert">{{ $message }}</div> @enderror
                </fieldset>
            </div>
        </div>
        <div class="card-footer bg-body d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                {{ $course ? 'Save changes' : 'Create course' }}
            </button>
            <a href="{{ route('courses.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <x-loading target="save" label="Saving" class="ms-2" />
        </div>
    </form>
</div>
