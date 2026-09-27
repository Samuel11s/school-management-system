<?php

namespace App\Livewire\Terms;

use App\Models\AcademicTerm;
use App\Services\CatalogService;
use App\Validation\AcademicTermRules;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?AcademicTerm $term = null;

    public string $name = '';

    public string $code = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $enrollment_opens_on = '';

    public string $enrollment_closes_on = '';

    public bool $is_current = false;

    public function mount(?AcademicTerm $term = null): void
    {
        if ($term?->exists) {
            $this->authorize('update', $term);
            $this->term = $term;
            $this->fill([
                'name' => $term->name,
                'code' => $term->code,
                'starts_on' => $term->starts_on->toDateString(),
                'ends_on' => $term->ends_on->toDateString(),
                'enrollment_opens_on' => $term->enrollment_opens_on->toDateString(),
                'enrollment_closes_on' => $term->enrollment_closes_on->toDateString(),
                'is_current' => $term->is_current,
            ]);

            return;
        }

        $this->authorize('create', AcademicTerm::class);
    }

    protected function rules(): array
    {
        return AcademicTermRules::rules($this->term);
    }

    protected function messages(): array
    {
        return AcademicTermRules::messages();
    }

    public function save(CatalogService $catalog): void
    {
        $this->term ? $this->authorize('update', $this->term) : $this->authorize('create', AcademicTerm::class);

        $this->code = strtoupper(trim($this->code));
        $catalog->saveTerm($this->validate(), $this->term);

        session()->flash('status', $this->term ? 'Term updated.' : 'Term created.');
        $this->redirectRoute('terms.index');
    }

    public function render(): View
    {
        return view('livewire.terms.form')->title($this->term ? 'Edit '.$this->term->name : 'Add term');
    }
}
