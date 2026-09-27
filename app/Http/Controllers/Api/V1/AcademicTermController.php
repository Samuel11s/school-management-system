<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AcademicTermRequest;
use App\Http\Resources\V1\AcademicTermResource;
use App\Models\AcademicTerm;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Academic terms
 */
class AcademicTermController extends ApiController
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * List academic terms.
     *
     * Filters: `filter[is_current]`. Sorts: `starts_on`, `name`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AcademicTerm::class);

        $terms = QueryBuilder::for(AcademicTerm::class)
            ->allowedFilters(AllowedFilter::exact('is_current'))
            ->allowedSorts('starts_on', 'name')
            ->defaultSort('-starts_on')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return AcademicTermResource::collection($terms);
    }

    /**
     * Create an academic term.
     */
    public function store(AcademicTermRequest $request): JsonResponse
    {
        Gate::authorize('create', AcademicTerm::class);

        return (new AcademicTermResource($this->catalog->saveTerm($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an academic term.
     */
    public function show(AcademicTerm $term): AcademicTermResource
    {
        Gate::authorize('view', $term);

        return new AcademicTermResource($term);
    }

    /**
     * Update an academic term.
     */
    public function update(AcademicTermRequest $request, AcademicTerm $term): AcademicTermResource
    {
        Gate::authorize('update', $term);

        return new AcademicTermResource($this->catalog->saveTerm($request->validated(), $term));
    }

    /**
     * Delete an academic term.
     *
     * Terms that have classes cannot be deleted.
     */
    public function destroy(AcademicTerm $term): Response
    {
        Gate::authorize('delete', $term);

        $this->catalog->deleteTerm($term);

        return response()->noContent();
    }
}
