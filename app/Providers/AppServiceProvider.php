<?php

namespace App\Providers;

use App\Services\GradeCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
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
    }
}
