<?php

use AmjadIqbal\PennantDashboard\Models\Rule;
use AmjadIqbal\PennantDashboard\Rollout;
use AmjadIqbal\PennantDashboard\Support\Preview;
use AmjadIqbal\PennantDashboard\Support\StoreInspector;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Lottery;
use Laravel\Pennant\Events\FeatureResolved;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
    Gate::define('managePennantDashboard', fn ($user = null) => false);
});

it('previews in read-only mode without storing anything', function () {
    config(['pennant-dashboard.read_only' => true]);
    $user = $this->user(1);
    Event::fake([FeatureResolved::class]);

    $this->get('/pennant/features/dark-mode?scope_type='.urlencode(User::class).'&scope_key='.$user->id)->assertOk();
    $this->get('/pennant/features/new-checkout?scope_type='.urlencode(User::class).'&scope_key='.$user->id)->assertOk();
    $this->get('/pennant?scope_type='.urlencode(User::class).'&scope_key='.$user->id)->assertOk();

    expect(DB::table('features')->count())->toBe(0);
    Event::assertNotDispatched(FeatureResolved::class);
});

it('shows the stored value when there is one, and the resolver otherwise', function () {
    [$a, $b] = $this->users(2);
    Feature::for($a)->activate('dark-mode');
    $preview = new Preview(new StoreInspector('database'));

    expect($preview->value('dark-mode', $a))->toMatchArray(['value' => true, 'source' => 'stored'])
        ->and($preview->value('dark-mode', $b))->toMatchArray(['value' => false, 'source' => 'resolver'])
        ->and(DB::table('features')->count())->toBe(1);
});

it('follows the rollout for a scope that was never checked, without storing it', function () {
    $users = $this->users(30);
    Rule::query()->create(['store' => 'database', 'feature' => 'new-checkout', 'percentage' => 50]);
    $preview = new Preview(new StoreInspector('database'));

    foreach ($users as $user) {
        expect($preview->value('new-checkout', $user)['value'])->toBe(Rollout::bucket('new-checkout', User::class.'|'.$user->id) < 50);
    }
    expect(DB::table('features')->count())->toBe(0);
});

it('runs a class feature before() hook first, as Pennant does', function () {
    Feature::define(PreviewHooked::class);
    $user = $this->user(1);
    Feature::for($user)->deactivate(PreviewHooked::class);

    expect((new Preview(new StoreInspector('database')))->value(PreviewHooked::class, $user))
        ->toMatchArray(['value' => true, 'source' => 'before']);
});

it('does not draw a lottery', function () {
    Feature::define('coin', Lottery::odds(1, 2));

    expect((new Preview(new StoreInspector('database')))->value('coin', $this->user(1)))
        ->toMatchArray(['value' => null, 'source' => 'lottery']);
});

it('reports a resolver that rejects the scope type instead of failing', function () {
    $result = (new Preview(new StoreInspector('database')))->value('team-billing', $this->user(1));

    expect($result['error'])->toContain('TypeError');
});

it('previews on the array driver', function () {
    config(['pennant.default' => 'array']);
    Feature::forgetDrivers();
    Feature::define('dark-mode', fn (User $user) => $user->id === 1);

    expect((new Preview(new StoreInspector('array')))->value('dark-mode', $this->user(1)))
        ->toMatchArray(['value' => true, 'source' => 'resolver']);
});

class PreviewHooked
{
    public function before(mixed $scope): ?bool
    {
        return true;
    }

    public function resolve(mixed $scope): bool
    {
        return false;
    }
}
