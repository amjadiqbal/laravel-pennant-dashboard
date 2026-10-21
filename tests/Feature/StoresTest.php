<?php

use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use AmjadIqbal\PennantDashboard\Rollout;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
});

it('keeps rollouts and overrides to the store they were set in', function () {
    $user = $this->user(1);

    $this->post('/pennant/actions/rollout', ['store' => 'database', 'feature' => 'new-checkout', 'percentage' => 100]);
    $this->post('/pennant/actions/scope', ['store' => 'database', 'feature' => 'new-checkout', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => $user->id]);

    expect(Rule::query()->pluck('store')->all())->toBe(['database'])
        ->and(Override::query()->pluck('store')->all())->toBe(['database']);

    $this->nextRequest();
    expect(Rollout::decide('new-checkout', $user))->toBeTrue()
        ->and(Rollout::decide('new-checkout', $user, false, 'array'))->toBeFalse()
        ->and(Rollout::decide('new-checkout', $this->user(2), false, 'array'))->toBeFalse();

    $this->get('/pennant?store=array')->assertOk()->assertDontSee('on for everyone');
    $this->get('/pennant?store=database')->assertOk()->assertSee('on for everyone');
});

it('lets the same feature have a different rollout per store', function () {
    Feature::store('array')->define('new-checkout', fn (User $user) => Rollout::decide('new-checkout', $user, false, 'array'));
    $this->post('/pennant/actions/rollout', ['store' => 'database', 'feature' => 'new-checkout', 'percentage' => 100]);
    $this->post('/pennant/actions/rollout', ['store' => 'array', 'feature' => 'new-checkout', 'percentage' => 0]);

    $this->nextRequest();
    $user = $this->user(1);
    expect(Rule::query()->count())->toBe(2)
        ->and(Rollout::decide('new-checkout', $user, false, 'database'))->toBeTrue()
        ->and(Rollout::decide('new-checkout', $user, true, 'array'))->toBeFalse();
});
