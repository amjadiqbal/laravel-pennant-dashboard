<?php

use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use AmjadIqbal\PennantDashboard\Rollout;
use AmjadIqbal\PennantDashboard\RolloutManager;
use Illuminate\Support\Facades\Schema;

it('gives every scope a fixed bucket from 0 to 99', function () {
    $buckets = array_map(fn ($i) => Rollout::bucket('new-checkout', "App\\Models\\User|{$i}"), range(1, 2000));

    expect(min($buckets))->toBeGreaterThanOrEqual(0)
        ->and(max($buckets))->toBeLessThanOrEqual(99)
        ->and(Rollout::bucket('new-checkout', 'App\\Models\\User|7'))->toBe(Rollout::bucket('new-checkout', 'App\\Models\\User|7'));

    // Roughly uniform: about a quarter of 2,000 scopes land under 25.
    $under = count(array_filter($buckets, fn ($b) => $b < 25));
    expect($under)->toBeGreaterThan(420)->toBeLessThan(580);
});

it('buckets the same scope differently per feature', function () {
    $same = 0;
    foreach (range(1, 500) as $i) {
        $same += (int) (Rollout::bucket('a', "u|{$i}") === Rollout::bucket('b', "u|{$i}"));
    }

    expect($same)->toBeLessThan(30);
});

it('falls back to the default when nothing is set', function () {
    expect(Rollout::decide('x', 'u|1', false))->toBeFalse()
        ->and(Rollout::decide('x', 'u|1', true))->toBeTrue()
        ->and(Rollout::decide('x', 'u|1', fn ($scope) => $scope === 'u|1'))->toBeTrue();
});

it('applies the percentage rule and lets an override win', function () {
    Rule::query()->create(['feature' => 'x', 'percentage' => 100]);
    expect(Rollout::decide('x', 'u|1'))->toBeTrue();

    $this->nextRequest();
    Rule::query()->where('feature', 'x')->update(['percentage' => 0]);
    expect(Rollout::decide('x', 'u|1', true))->toBeFalse();

    $this->nextRequest();
    Override::query()->create(['feature' => 'x', 'scope' => 'u|1', 'value' => 'true']);
    expect(Rollout::decide('x', 'u|1'))->toBeTrue()
        ->and(Rollout::decide('x', 'u|2'))->toBeFalse();
});

it('puts exactly the scopes under the percentage in the rollout', function () {
    Rule::query()->create(['feature' => 'x', 'percentage' => 30]);

    foreach (range(1, 200) as $i) {
        expect(Rollout::decide('x', "u|{$i}"))->toBe(Rollout::bucket('x', "u|{$i}") < 30);
    }
});

it('reads rules once per request', function () {
    Rule::query()->create(['feature' => 'x', 'percentage' => 100]);
    expect(Rollout::decide('x', 'u|1'))->toBeTrue();

    Rule::query()->where('feature', 'x')->update(['percentage' => 0]);
    expect(Rollout::decide('x', 'u|1'))->toBeTrue(); // memoised

    $this->nextRequest();
    expect(Rollout::decide('x', 'u|1'))->toBeFalse();
});

it('does not break the app when the tables are missing', function () {
    Schema::drop('pennant_dashboard_rules');
    Schema::drop('pennant_dashboard_overrides');
    app(RolloutManager::class)->flush();

    expect(Rollout::decide('x', 'u|1', true))->toBeTrue();
});

it('treats scopes Pennant cannot serialize as outside the rollout', function () {
    Rule::query()->create(['feature' => 'x', 'percentage' => 50]);

    expect(Rollout::decide('x', new stdClass, true))->toBeFalse();
});
