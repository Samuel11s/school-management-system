<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Column sorting for list components. Only columns listed in sortableColumns()
 * are accepted, so the sort field can never be used for SQL injection.
 * Components using it must also use Livewire's WithPagination.
 */
trait WithSorting
{
    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    /**
     * @return list<string>
     */
    abstract protected function sortableColumns(): array;

    abstract protected function defaultSortField(): string;

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortableColumns(), true)) {
            return;
        }

        $this->sortDirection = $this->currentSortField() === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;

        $this->resetPage();
    }

    protected function currentSortField(): string
    {
        return in_array($this->sortField, $this->sortableColumns(), true) ? $this->sortField : $this->defaultSortField();
    }

    protected function currentSortDirection(): string
    {
        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }
}
