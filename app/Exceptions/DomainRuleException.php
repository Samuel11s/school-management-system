<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A business rule was violated (e.g. a class is full or a grade is out of range).
 *
 * Rendered like a validation error (HTTP 422) keyed by the offending field so
 * that web forms and API clients handle it the same way as invalid input.
 */
class DomainRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'general')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => [$this->field => [$this->getMessage()]],
            ], 422);
        }

        return back()->withInput()->withErrors([$this->field => $this->getMessage()]);
    }
}
