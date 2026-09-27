<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Readiness probe: verifies the dependencies needed to serve traffic.
 * (Liveness is served by Laravel's built-in /up route.)
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection()->select('select 1')),
            'cache' => $this->check(function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';
                throw_unless($ok, new \RuntimeException('Cache round trip failed.'));
            }),
            'queue' => $this->check(fn () => Queue::size()),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    /**
     * Details are logged, never returned, so the endpoint cannot leak
     * connection strings or credentials.
     */
    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $e) {
            Log::warning('health.check_failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return 'fail';
        }
    }
}
