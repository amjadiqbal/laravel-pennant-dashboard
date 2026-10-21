<?php

use AmjadIqbal\PennantDashboard\Rollout;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;

beforeEach(function () {
    config(['pennant.default' => 'array']);
    Feature::forgetDrivers();
    Feature::define('new-checkout', fn (User $user) => Rollout::decide('new-checkout', $user, false));
    Feature::define('dark-mode', fn (User $user) => false);
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
});

it('reports what the array driver supports', function () {
    $this->get('/pennant')->assertOk()
        ->assertSee('What the <code>array</code> driver supports', false)
        ->assertSee('forgets everything after each request');
});

it('keeps overrides and rollouts for features that call Rollout::decide()', function () {
    [$a, $b] = $this->users(2);

    $this->post('/pennant/actions/scope', ['feature' => 'new-checkout', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => $a->id])
        ->assertSessionHas('pennant-dashboard.status');

    // A new request: the array driver has forgotten everything, the override has not.
    Feature::forgetDrivers();
    Feature::define('new-checkout', fn (User $user) => Rollout::decide('new-checkout', $user, false));
    $this->nextRequest();

    expect(Feature::for($a)->active('new-checkout'))->toBeTrue()
        ->and(Feature::for($b)->active('new-checkout'))->toBeFalse();

    $this->post('/pennant/actions/everyone', ['feature' => 'new-checkout', 'active' => 1]);
    Feature::forgetDrivers();
    Feature::define('new-checkout', fn (User $user) => Rollout::decide('new-checkout', $user, false));
    $this->nextRequest();

    expect(Feature::for($b)->active('new-checkout'))->toBeTrue();
});

it('says when a change cannot stick on the array driver', function () {
    $user = $this->user(1);

    $this->post('/pennant/actions/scope', ['feature' => 'dark-mode', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => $user->id])
        ->assertSessionHas('pennant-dashboard.status', fn ($m) => str_contains($m, 'will not stick'));
});

it('lists overrides instead of stored rows on the feature page', function () {
    $user = $this->user(1);
    $this->post('/pennant/actions/scope', ['feature' => 'new-checkout', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => $user->id]);

    $this->get('/pennant/features/new-checkout')->assertOk()
        ->assertSee('Overrides set here')
        ->assertSee('User 1');
});
