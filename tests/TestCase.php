<?php

namespace AmjadIqbal\PennantDashboard\Tests;

use AmjadIqbal\PennantDashboard\PennantDashboardServiceProvider;
use AmjadIqbal\PennantDashboard\Rollout;
use AmjadIqbal\PennantDashboard\Support\FeatureCatalog;
use AmjadIqbal\PennantDashboard\Support\SchemaStatus;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\Team;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PennantServiceProvider::class, PennantDashboardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('pennant.default', 'database');
        $app['config']->set('pennant-dashboard.scopes', [User::class => ['label' => ['name', 'email']], Team::class => ['label' => ['name']]]);
        $app['config']->set('pennant-dashboard.discover', ['enabled' => true, 'namespace' => 'AmjadIqbal\\PennantDashboard\\Tests\\Fixtures\\Features', 'path' => __DIR__.'/Fixtures/Features']);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('x');
            $table->timestamps();
        });
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        $this->loadMigrationsFrom(dirname(__DIR__).'/vendor/laravel/pennant/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        FeatureCatalog::resetDiscovery();
        SchemaStatus::resetWarnings();

        Feature::define('new-checkout', fn (User $user) => Rollout::decide('new-checkout', $user, false));
        Feature::define('dark-mode', fn (User $user) => false);
        Feature::define('team-billing', fn (Team $team) => $team->name === 'Acme');
    }

    protected function user(int $n = 1): User
    {
        return User::query()->create(['name' => "User {$n}", 'email' => "user{$n}@example.com"]);
    }

    /** @return list<User> */
    protected function users(int $count): array
    {
        $users = [];
        for ($i = 1; $i <= $count; $i++) {
            $users[] = $this->user($i);
        }

        return $users;
    }

    /** Simulate a fresh request: Pennant's in-memory cache and the rollout memo are dropped. */
    protected function nextRequest(): void
    {
        Feature::flushCache();
        $this->app->forgetScopedInstances();
    }
}
