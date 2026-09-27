<?php

namespace App\Providers;

use App\Models\User;
use App\Services\GradeCalculator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GradeCalculator::class, fn () => new GradeCalculator(
            config('school.grading.scale'),
            (float) config('school.grading.passing_score'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly in development instead of silently ignoring mistakes.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());

        $this->configureRateLimiting();
        $this->configureApiDocumentation();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('school.api.rate_limit', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        // Brute-force protection for API token requests.
        RateLimiter::for('api-login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });
    }

    private function configureApiDocumentation(): void
    {
        // Outside the local environment the docs are limited to administrators.
        Gate::define('viewApiDocs', fn (?User $user = null) => $user?->isAdmin() ?? false);

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }
}
