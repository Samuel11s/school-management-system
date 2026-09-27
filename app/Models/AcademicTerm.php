<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AcademicTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'starts_on', 'ends_on', 'enrollment_opens_on', 'enrollment_closes_on', 'is_current'])]
class AcademicTerm extends Model
{
    /** @use HasFactory<AcademicTermFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'enrollment_opens_on' => 'date',
            'enrollment_closes_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Only one term can be flagged as current at a time.
        static::saved(function (AcademicTerm $term) {
            if ($term->is_current && ($term->wasRecentlyCreated || $term->wasChanged('is_current'))) {
                static::query()
                    ->whereKeyNot($term->getKey())
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }
        });
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function isEnrollmentOpen(?CarbonInterface $on = null): bool
    {
        $on = ($on ?? now())->copy()->startOfDay();

        return $on->betweenIncluded($this->enrollment_opens_on, $this->enrollment_closes_on);
    }

    public function includesDate(CarbonInterface $date): bool
    {
        return $date->copy()->startOfDay()->betweenIncluded($this->starts_on, $this->ends_on);
    }

    /**
     * @param  Builder<AcademicTerm>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }
}
