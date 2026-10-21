<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard;

use AmjadIqbal\PennantDashboard\Http\Authorize;
use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class PennantDashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pennant-dashboard.php', 'pennant-dashboard');
        $this->app->scoped(RolloutManager::class);
        $this->app->singleton(AuditRecorder::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pennant-dashboard');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/pennant-dashboard.php' => config_path('pennant-dashboard.php')], 'pennant-dashboard-config');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'pennant-dashboard-migrations');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/pennant-dashboard')], 'pennant-dashboard-views');
        }

        if (config('pennant-dashboard.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Local only until the app defines its own gates (app providers boot after this one, so theirs win).
        if (! Gate::has('viewPennantDashboard')) {
            Gate::define('viewPennantDashboard', fn ($user = null): bool => $this->app->environment('local'));
        }
        if (! Gate::has('managePennantDashboard')) {
            Gate::define('managePennantDashboard', fn ($user = null): bool => Gate::forUser($user)->check('viewPennantDashboard', [request()]));
        }

        if (config('pennant-dashboard.audit.code_changes', true)) {
            Event::listen(AuditRecorder::events(), fn (object $event) => $this->app->make(AuditRecorder::class)->handle($event));
        }

        if (config('pennant-dashboard.enabled', true)) {
            $this->registerRoutes();
        }

        AboutCommand::add('Pennant Dashboard', fn (): array => [
            'Version' => self::version(),
            'Dashboard' => config('pennant-dashboard.enabled', true) ? '/'.trim((string) config('pennant-dashboard.path'), '/') : 'off',
            'Support' => 'https://amjad.com.pk/contact?utm_source=artisan&utm_medium=about&utm_campaign=laravel-pennant-dashboard',
        ]);
    }

    private function registerRoutes(): void
    {
        $middleware = array_values(array_filter((array) config('pennant-dashboard.middleware', ['web']), 'is_string'));

        Route::group([
            'prefix' => trim((string) config('pennant-dashboard.path', 'pennant'), '/'),
            'domain' => config('pennant-dashboard.domain'),
            'middleware' => [...$middleware, Authorize::class],
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
    }

    public static function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('amjadiqbal/laravel-pennant-dashboard') ?? 'dev';
        } catch (Throwable) {
            return 'dev';
        }
    }
}
