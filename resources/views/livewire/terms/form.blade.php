<div>
    <x-page-header :title="$term ? 'Edit '.$term->name : 'Add term'" :back="route('terms.index')" />

    <form wire:submit="save" novalidate class="card shadow-sm" style="max-width: 44rem">
        <div class="card-body">
            <div class="row">
                <x-form.input class="col-md-8" name="name" label="Name" required help="For example Fall 2026." />
                <x-form.input class="col-md-4" name="code" label="Code" required help="For example FA2026." />
                <x-form.input class="col-md-6" name="starts_on" type="date" label="First day" required />
                <x-form.input class="col-md-6" name="ends_on" type="date" label="Last day" required />
                <x-form.input class="col-md-6" name="enrollment_opens_on" type="date" label="Enrollment opens" required />
                <x-form.input class="col-md-6" name="enrollment_closes_on" type="date" label="Enrollment closes" required />
                <x-form.checkbox class="col-12" name="is_current" label="This is the current term"
                                 help="Only one term can be current; the previous current term is unset automatically." />
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                {{ $term ? 'Save changes' : 'Create term' }}
            </button>
            <a href="{{ route('terms.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
