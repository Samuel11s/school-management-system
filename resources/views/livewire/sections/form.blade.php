<div>
    <x-page-header :title="$section ? 'Edit '.$section->name : 'Add class'"
                   :back="$section ? route('sections.show', $section) : route('sections.index')" />

    <form wire:submit="save" novalidate class="card shadow-sm" style="max-width: 48rem">
        <div class="card-body">
            <div class="row">
                <x-form.select class="col-md-8" name="course_id" label="Course" :options="$courses" placeholder="Select a course" required />
                <x-form.input class="col-md-4" name="code" label="Class code" required help="For example A or B." />
                <x-form.select class="col-md-6" name="academic_term_id" label="Term" :options="$terms" placeholder="Select a term" required />
                <x-form.select class="col-md-6" name="teacher_id" label="Teacher" :options="$teachers" placeholder="Unassigned" />
                <x-form.input class="col-md-6" name="schedule" label="Schedule" help="For example Mon/Wed 09:00-10:30." />
                <x-form.input class="col-md-6" name="room" label="Room" />
                <x-form.input class="col-md-6" name="capacity" type="number" min="1" max="500" label="Capacity" required />
                <x-form.select class="col-md-6" name="status" label="Status" :options="$statuses" required
                               help="Cancelling a class drops every active enrollment." />
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                {{ $section ? 'Save changes' : 'Create class' }}
            </button>
            <a href="{{ $section ? route('sections.show', $section) : route('sections.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
