<?php

namespace App\Livewire\Terms;

use App\Exceptions\DomainRuleException;
use App\Models\AcademicTerm;
use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Academic terms')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public function mount(): void
    {
        $this->authorize('create', AcademicTerm::class);
    }

    public function makeCurrent(int $termId): void
    {
        $term = AcademicTerm::query()->findOrFail($termId);
        $this->authorize('update', $term);

        $term->update(['is_current' => true]);
        $this->dispatch('notify', message: "{$term->name} is now the current term.");
    }

    public function delete(int $termId, CatalogService $catalog): void
    {
        $term = AcademicTerm::query()->findOrFail($termId);
        $this->authorize('delete', $term);

        try {
            $catalog->deleteTerm($term);
            $this->dispatch('notify', message: "{$term->name} deleted.");
        } catch (DomainRuleException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'danger');
        }
    }

    public function render(): View
    {
        return view('livewire.terms.index', [
            'terms' => AcademicTerm::query()->withCount('sections')->orderByDesc('starts_on')->paginate(15),
        ]);
    }
}
