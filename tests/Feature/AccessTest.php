<?php

use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;

it('is closed outside the local environment by default', function () {
    $this->get('/pennant')->assertForbidden();
});

it('opens in the local environment', function () {
    $this->app['env'] = 'local';

    $this->get('/pennant')->assertOk()->assertSee('Feature flags');
});

it('uses the app gate', function () {
    Gate::define('viewPennantDashboard', fn (User $user) => $user->email === 'admin@example.com');

    $this->get('/pennant')->assertForbidden();
    $this->actingAs($this->user())->get('/pennant')->assertForbidden();
    $this->actingAs(User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com']))->get('/pennant')->assertOk();
});

it('needs the manage gate to change anything', function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
    Gate::define('managePennantDashboard', fn ($user = null) => false);

    $this->get('/pennant')->assertOk()->assertSee('read-only');
    $this->post('/pennant/actions/everyone', ['feature' => 'dark-mode', 'active' => 1])->assertForbidden();
});

it('blocks every change in read-only mode', function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
    config(['pennant-dashboard.read_only' => true]);

    $this->post('/pennant/actions/everyone', ['feature' => 'dark-mode', 'active' => 1])->assertForbidden();
});

it('rejects an unknown store and an unknown feature', function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);

    $this->get('/pennant?store=nope')->assertNotFound();
    $this->get('/pennant/features/nope')->assertNotFound();
});

it('adds an about entry with the version and support link', function () {
    $this->artisan('about')->expectsOutputToContain('Pennant Dashboard')->assertSuccessful();
});
