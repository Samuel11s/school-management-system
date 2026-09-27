<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlates log entries of a request: reuses a well-formed incoming
 * X-Request-Id (from a proxy or client) or generates one, adds it to the
 * log context and echoes it in the response.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9\-_.]{8,128}$/', $incoming) ? $incoming : (string) Str::uuid();

        Log::withContext([
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        $response = $next($request);

        // Identify the user by id only; no personal data in logs.
        if ($userId = $request->user()?->getAuthIdentifier()) {
            Log::withContext(['user_id' => $userId]);
        }

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
