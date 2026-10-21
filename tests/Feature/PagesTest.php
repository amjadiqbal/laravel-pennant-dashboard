<?php

use AmjadIqbal\PennantDashboard\Tests\Fixtures\Features\NewApi;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\Team;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
});

it('lists closure and discovered class features with their kind and opt-in status', function () {
    $this->get('/pennant')->assertOk()
        ->assertSee('new-checkout')
        ->assertSee('dark-mode')
        ->assertSee('team-billing')
        ->assertSee(NewApi::class)
        ->assertSee('plain-banner')
        ->assertSee('rollout-ready')
        ->assertSee('closure');
});

it('counts stored values and flags stale features', function () {
    foreach ($this->users(3) as $user) {
        Feature::for($user)->active('dark-mode');
    }
    Feature::for(User::query()->first())->activate('dark-mode');
    DB::table('features')->insert(['name' => 'gone', 'scope' => 'x', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()]);

    $this->get('/pennant')->assertOk()
        ->assertSee('1</b> on · 2 off', false)
        ->assertSee('stale');
});

it('shows stored values with readable scope labels and filters them', function () {
    [$a, $b] = $this->users(2);
    Feature::for($a)->activate('dark-mode');
    Feature::for($b)->deactivate('dark-mode');

    $this->get('/pennant/features/dark-mode')->assertOk()
        ->assertSee('User 1 · user1@example.com')
        ->assertSee('User 2 · user2@example.com');

    $this->get('/pennant/features/dark-mode?value=on')->assertOk()
        ->assertSee('user1@example.com')
        ->assertDontSee('user2@example.com');
});

it('checks the value one scope sees and explains why', function () {
    $user = $this->user(3);

    $this->get('/pennant/features/dark-mode?scope_type='.urlencode(User::class).'&scope_key='.$user->id)->assertOk()
        ->assertSee('Resolved just now')
        ->assertSee('Rollout bucket');
});

it('shows what one user sees across all features', function () {
    $user = $this->user(4);

    $this->get('/pennant?scope_type='.urlencode(User::class).'&scope_key='.$user->id)->assertOk()
        ->assertSee('What does one scope see?')
        ->assertSee('dark-mode')
        ->assertDontSee('TypeError');
});

it('searches users by name or email', function () {
    $this->users(3);

    $this->get('/pennant/features/dark-mode?search_type='.urlencode(User::class).'&q=user2')->assertOk()
        ->assertSee('data-pick-key="2"', false);
});

it('opens a class feature by its name in the URL', function () {
    $this->get('/pennant/features/'.rawurlencode(NewApi::class))->assertOk()->assertSee('rollout-ready')->assertDontSee('opt in', false);
    $this->get('/pennant/features/plain-banner')->assertOk()->assertSee('use HasRollout;', false)->assertSee('class PlainBanner', false);
});

it('shows the opt-in snippet for a closure feature that does not call Rollout::decide()', function () {
    $this->get('/pennant/features/dark-mode')->assertOk()->assertSee("Rollout::decide('dark-mode', \$user, default: false)", false);
});

it('renders the audit log', function () {
    Feature::for($this->user(1))->activate('dark-mode');

    $this->get('/pennant/audit')->assertOk()->assertSee('artisan / console')->assertSee('dark-mode');
    $this->get('/pennant/audit?source=dashboard')->assertOk()->assertSee('No changes recorded yet.');
});

it('starts the scope pickers on the type the feature is stored for', function () {
    $team = Team::query()->create(['name' => 'Acme']);
    Feature::for($team)->active('team-billing');

    $html = $this->get('/pennant/features/team-billing')->assertOk()->getContent();

    expect(substr_count($html, 'value="'.e(Team::class).'" selected'))->toBeGreaterThanOrEqual(2);
});

it('marks only the picker fields for the search pick script, not the hidden row fields', function () {
    Feature::for($this->user(1))->activate('dark-mode');

    $html = $this->get('/pennant/features/dark-mode')->assertOk()->getContent();

    expect(substr_count($html, 'name="scope_key" data-scope-picker'))->toBe(1)
        ->and($html)->toContain('<input type="hidden" name="scope_key" value="'.e(User::class).'|1">');
});
