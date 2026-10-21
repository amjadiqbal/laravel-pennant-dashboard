<?php

use AmjadIqbal\PennantDashboard\Models\AuditEntry;
use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use AmjadIqbal\PennantDashboard\Rollout;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\Features\NewApi;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\Team;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
    $this->actingAs($this->admin);
});

function storedValue(string $feature, string $scope): mixed
{
    $value = DB::table('features')->where('name', $feature)->where('scope', $scope)->value('value');

    return $value === null ? null : json_decode($value, true);
}

it('activates a feature for one user and records who did it', function () {
    $user = $this->user(5);

    $this->post('/pennant/actions/scope', ['feature' => 'dark-mode', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => $user->id])
        ->assertRedirect()->assertSessionHas('pennant-dashboard.status');

    $this->nextRequest();
    expect(Feature::for($user)->active('dark-mode'))->toBeTrue()
        ->and(Feature::for($this->user(6))->active('dark-mode'))->toBeFalse()
        ->and(Override::query()->where('feature', 'dark-mode')->count())->toBe(1);

    $entry = AuditEntry::query()->sole();
    expect($entry->action)->toBe('activate')
        ->and($entry->scope)->toBe(User::class.'|'.$user->id)
        ->and($entry->actor)->toBe('Admin · admin@example.com')
        ->and($entry->source)->toBe('dashboard')
        ->and($entry->new_value)->toBe('true');
});

it('activates for a team, deactivates and forgets', function () {
    $team = Team::query()->create(['name' => 'Initech']);

    $this->post('/pennant/actions/scope', ['feature' => 'team-billing', 'action' => 'activate', 'scope_type' => Team::class, 'scope_key' => $team->id]);
    expect(storedValue('team-billing', Team::class.'|'.$team->id))->toBeTrue();

    $this->post('/pennant/actions/scope', ['feature' => 'team-billing', 'action' => 'deactivate', 'scope_type' => 'raw', 'scope_key' => Team::class.'|'.$team->id]);
    expect(storedValue('team-billing', Team::class.'|'.$team->id))->toBeFalse();

    $this->post('/pennant/actions/scope', ['feature' => 'team-billing', 'action' => 'forget', 'scope_type' => Team::class, 'scope_key' => $team->id]);
    expect(storedValue('team-billing', Team::class.'|'.$team->id))->toBeNull()
        ->and(Override::query()->count())->toBe(0)
        ->and(AuditEntry::query()->pluck('action')->all())->toBe(['activate', 'deactivate', 'forget']);
});

it('rejects a missing model', function () {
    $this->post('/pennant/actions/scope', ['feature' => 'dark-mode', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => 999])
        ->assertSessionHasErrors('scope_key');
});

it('activates for everyone the Pennant way for a plain feature', function () {
    [$a, $b] = $this->users(2);
    Feature::for($a)->active('dark-mode');
    Feature::for($b)->active('dark-mode');

    $this->post('/pennant/actions/everyone', ['feature' => 'dark-mode', 'active' => 1])->assertSessionHas('pennant-dashboard.status');

    expect(storedValue('dark-mode', User::class.'|'.$a->id))->toBeTrue()
        ->and(storedValue('dark-mode', User::class.'|'.$b->id))->toBeTrue()
        ->and(Rule::query()->count())->toBe(0);

    // Pennant's activateForEveryone only covers stored scopes: a new user still gets the resolver default.
    $this->nextRequest();
    expect(Feature::for($this->user(3))->active('dark-mode'))->toBeFalse();
});

it('makes "everyone" cover new scopes for a feature that calls Rollout::decide()', function () {
    $this->post('/pennant/actions/everyone', ['feature' => 'new-checkout', 'active' => 1]);

    $this->nextRequest();
    expect(Rule::query()->value('percentage'))->toBe(100)
        ->and(Feature::for($this->user(9))->active('new-checkout'))->toBeTrue();

    $this->post('/pennant/actions/everyone', ['feature' => 'new-checkout', 'active' => 0]);
    $this->nextRequest();
    expect(Feature::for(User::query()->where('email', 'user9@example.com')->sole())->active('new-checkout'))->toBeFalse()
        ->and(Feature::for($this->user(10))->active('new-checkout'))->toBeFalse();
});

it('rolls out to a sticky 25% and keeps overrides', function () {
    $users = $this->users(400);
    foreach ($users as $user) {
        Feature::for($user)->active('new-checkout'); // all stored as false
    }
    $pinned = $users[0];
    $this->post('/pennant/actions/scope', ['feature' => 'new-checkout', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => $pinned->id]);

    $this->post('/pennant/actions/rollout', ['feature' => 'new-checkout', 'percentage' => 25])->assertSessionHas('pennant-dashboard.status');

    $this->nextRequest();
    $on = collect($users)->filter(fn ($u) => Feature::for($u)->active('new-checkout'));
    expect($on->count())->toBeGreaterThan(70)->toBeLessThan(130)
        ->and($on->contains($pinned))->toBeTrue();

    foreach (array_slice($users, 1) as $user) {
        expect(Feature::for($user)->active('new-checkout'))->toBe(Rollout::bucket('new-checkout', User::class.'|'.$user->id) < 25);
    }

    // Sticky across requests, and new users follow the same rule.
    $this->nextRequest();
    expect(collect($users)->filter(fn ($u) => Feature::for($u)->active('new-checkout'))->count())->toBe($on->count());
    $new = $this->user(401);
    expect(Feature::for($new)->active('new-checkout'))->toBe(Rollout::bucket('new-checkout', User::class.'|'.$new->id) < 25);

    // Raising the percentage keeps everyone who already had it.
    $this->post('/pennant/actions/rollout', ['feature' => 'new-checkout', 'percentage' => 50]);
    $this->nextRequest();
    foreach ($on as $user) {
        expect(Feature::for($user)->active('new-checkout'))->toBeTrue();
    }

    $rollout = AuditEntry::query()->where('action', 'rollout')->latest('id')->first();
    expect($rollout->old_value)->toBe('25')->and($rollout->new_value)->toBe('50');
});

it('removes a rollout and lets the code decide again, keeping overrides', function () {
    $users = $this->users(40);
    $this->post('/pennant/actions/rollout', ['feature' => 'new-checkout', 'percentage' => 100]);
    foreach ($users as $user) {
        Feature::for($user)->active('new-checkout');
    }
    $this->post('/pennant/actions/scope', ['feature' => 'new-checkout', 'action' => 'activate', 'scope_type' => User::class, 'scope_key' => $users[3]->id]);

    $this->post('/pennant/actions/rollout', ['feature' => 'new-checkout', 'clear' => 1]);

    expect(Rule::query()->count())->toBe(0)
        ->and(DB::table('features')->where('name', 'new-checkout')->count())->toBe(1);

    $this->nextRequest();
    expect(Feature::for($users[3])->active('new-checkout'))->toBeTrue()
        ->and(Feature::for($users[4])->active('new-checkout'))->toBeFalse();
});

it('rolls out a class-based feature that uses the HasRollout trait', function () {
    $user = $this->user(1);
    Feature::define(NewApi::class);

    $this->post('/pennant/actions/rollout', ['feature' => NewApi::class, 'percentage' => 100]);
    $this->nextRequest();

    expect(Feature::for($user)->active(NewApi::class))->toBeTrue();
});

it('purges one feature with or without its rule', function () {
    $user = $this->user(1);
    $this->post('/pennant/actions/rollout', ['feature' => 'new-checkout', 'percentage' => 40]);
    Feature::for($user)->active('new-checkout');
    Feature::for($user)->active('dark-mode');

    $this->post('/pennant/actions/purge', ['feature' => 'new-checkout']);
    expect(DB::table('features')->where('name', 'new-checkout')->count())->toBe(0)
        ->and(DB::table('features')->where('name', 'dark-mode')->count())->toBe(1)
        ->and(Rule::query()->count())->toBe(1);

    $this->post('/pennant/actions/purge', ['feature' => 'new-checkout', 'with_rule' => 1]);
    expect(Rule::query()->count())->toBe(0);
});

it('purges stale features that are stored but no longer defined', function () {
    DB::table('features')->insert(['name' => 'old-flag', 'scope' => 'x', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()]);
    Feature::for($this->user(1))->active('dark-mode');

    $this->post('/pennant/actions/purge-stale', [])->assertSessionHas('pennant-dashboard.status', 'Purged 1 stale feature: old-flag.');

    expect(DB::table('features')->pluck('name')->all())->toBe(['dark-mode']);
});

it('logs changes made by the app code once, and dashboard changes once', function () {
    $user = $this->user(1);
    Feature::for($user)->activate('dark-mode');
    Feature::purge('dark-mode');

    expect(AuditEntry::query()->pluck('source', 'action')->all())->toBe(['set' => 'console', 'purge' => 'console']);

    $this->post('/pennant/actions/everyone', ['feature' => 'dark-mode', 'active' => 1]);
    expect(AuditEntry::query()->where('source', 'dashboard')->count())->toBe(1)
        ->and(AuditEntry::query()->count())->toBe(3);
});
